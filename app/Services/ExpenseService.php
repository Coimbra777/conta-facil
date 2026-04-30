<?php

namespace App\Services;

use App\Exceptions\HttpApiException;
use App\Models\Charge;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\User;
use App\Support\ChargeStatusTransition;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpenseService
{
    public function __construct(private NotificationService $notificationService) {}

    /**
     * Cria cobrança sem participantes (charges). Valores por participante em addParticipantsToExpense.
     * Fluxo principal: sem equipe (`team_id` nulo).
     */
    public function createExpenseForUser(User $creator, array $data): Expense
    {
        return DB::transaction(function () use ($creator, $data) {
            $expense = Expense::create([
                'team_id' => null,
                'created_by' => $creator->id,
                'description' => $data['description'],
                'total_amount' => $data['total_amount'],
                'amount_per_member' => 0,
                'due_date' => $data['due_date'],
                'pix_key' => $data['pix_key'],
                'pix_qr_code' => $data['pix_qr_code'] ?? null,
                'status' => 'open',
            ]);

            return $expense->fresh()->load(['charges.expenseParticipant', 'charges.teamMember']);
        });
    }

    public function updateExpense(Expense $expense, array $data): Expense
    {
        if ($expense->status === 'closed') {
            throw new \DomainException('Esta despesa foi finalizada e nao aceita mais alteracoes.');
        }

        $oldTotal = (float) $expense->total_amount;
        $newTotal = (float) $data['total_amount'];
        $totalChanged = abs($newTotal - $oldTotal) > 0.001;

        if ($totalChanged && $expense->charges()->where('status', '!=', 'pending')->exists()) {
            throw new \DomainException(
                'Nao e possivel alterar o valor total enquanto houver cobranca com status diferente de pendente.'
            );
        }

        return DB::transaction(function () use ($expense, $data, $totalChanged) {
            $expense->update([
                'description' => $data['description'],
                'total_amount' => $data['total_amount'],
                'due_date' => $data['due_date'],
                'pix_key' => $data['pix_key'],
                'pix_qr_code' => $data['pix_qr_code'] ?? null,
            ]);
            $expense->refresh();

            if ($totalChanged) {
                $this->redistributeChargeAmounts($expense);
            } else {
                $expense->charges()->update([
                    'description' => $expense->description,
                    'due_date' => $expense->due_date,
                ]);
            }

            return $expense->fresh()->load(['charges.expenseParticipant', 'charges.teamMember']);
        });
    }

    /**
     * @param  list<array{name: string, phone: string}>  $participants  phones apenas digitos
     */
    public function addParticipantsToExpense(Expense $expense, array $participants): Expense
    {
        if ($expense->status === 'closed') {
            throw new \DomainException('Esta despesa foi finalizada e nao aceita mais alteracoes.');
        }

        if ($expense->charges()->where('status', '!=', 'pending')->exists()) {
            throw new \DomainException(
                'Não é possível redistribuir valores pois já existem pagamentos em andamento.'
            );
        }

        $result = DB::transaction(function () use ($expense, $participants) {
            $newChargeIds = [];

            foreach ($participants as $p) {
                $phone = PhoneNormalizer::digits($p['phone'] ?? '');
                $name = trim((string) ($p['name'] ?? ''));
                if ($phone === '' || strlen($phone) < 10 || $name === '') {
                    continue;
                }

                if ($expense->charges()->whereHas('expenseParticipant', function ($q) use ($phone): void {
                    $q->where('phone_normalized', $phone);
                })->exists()) {
                    continue;
                }

                $participant = ExpenseParticipant::create([
                    'expense_id' => $expense->id,
                    'name' => $name,
                    'phone' => $phone,
                    'phone_normalized' => $phone,
                    'email' => null,
                    'amount' => 0,
                    'metadata' => null,
                ]);

                $charge = Charge::create([
                    'team_member_id' => null,
                    'user_id' => null,
                    'expense_participant_id' => $participant->id,
                    'expense_id' => $expense->id,
                    'description' => $expense->description,
                    'amount' => 0.0,
                    'due_date' => $expense->due_date,
                    'status' => 'pending',
                ]);
                $newChargeIds[] = $charge->id;
            }

            $fresh = $expense->fresh()->load(['charges.expenseParticipant', 'charges.teamMember']);
            $this->applyExplicitParticipantAmounts($fresh, $participants);

            return [
                'expense' => $fresh->load(['charges.expenseParticipant', 'charges.teamMember']),
                'newChargeIds' => $newChargeIds,
            ];
        });

        foreach ($result['newChargeIds'] as $chargeId) {
            $charge = Charge::query()
                ->with(['expenseParticipant', 'teamMember'])
                ->find($chargeId);
            if ($charge && ($charge->expenseParticipant || $charge->teamMember)) {
                try {
                    $this->notificationService->notifyChargeRecipient(
                        $charge->fresh(),
                        $result['expense']->fresh(),
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed to notify new charge', [
                        'charge_id' => $chargeId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $result['expense'];
    }

    /**
     * Atribui valores por participante (telefone). A soma deve igualar o total da despesa.
     *
     * @param  list<array{name: string, phone: string, amount?: float|int|string|null}>  $participants
     */
    public function applyExplicitParticipantAmounts(Expense $expense, array $participants): void
    {
        $charges = $expense->charges()->with(['expenseParticipant', 'teamMember'])->orderBy('id')->get();
        ChargeStatusTransition::assertAllPendingForRedistribution($charges);

        $amountByPhone = [];
        foreach ($participants as $p) {
            $phone = PhoneNormalizer::digits($p['phone'] ?? '');
            if ($phone === '') {
                continue;
            }
            $amount = isset($p['amount']) ? round((float) $p['amount'], 2) : null;
            if ($amount === null || $amount <= 0) {
                throw new \DomainException('Cada participante deve ter um valor maior que zero.');
            }
            $amountByPhone[$phone] = $amount;
        }

        $expectedSum = round((float) $expense->total_amount, 2);
        $sum = round(array_sum($amountByPhone), 2);
        if (abs($sum - $expectedSum) > 0.02) {
            throw new \DomainException('A soma dos valores dos participantes deve ser igual ao valor total da cobrança.');
        }

        if (count($amountByPhone) !== $charges->count()) {
            throw new \DomainException('Informe exatamente um valor para cada participante da cobrança.');
        }

        foreach ($charges as $charge) {
            $participantPhone = PhoneNormalizer::digits(
                (string) ($charge->expenseParticipant?->phone ?? $charge->teamMember?->phone ?? '')
            );
            if ($participantPhone === '' || ! isset($amountByPhone[$participantPhone])) {
                throw new \DomainException('Defina o valor para cada participante da cobrança.');
            }
            $amt = $amountByPhone[$participantPhone];
            $charge->update([
                'amount' => $amt,
                'description' => $expense->description,
                'due_date' => $expense->due_date,
            ]);
            $charge->expenseParticipant?->update(['amount' => $amt]);
        }

        $count = $charges->count();
        $avg = $count > 0 ? round($expectedSum / $count, 2) : 0;
        $expense->update(['amount_per_member' => $avg]);
    }

    /**
     * Exclui despesa somente se todas as cobranças estiverem pendentes (sem comprovante/pagamento).
     */
    public function deleteExpenseIfAllowed(Expense $expense): void
    {
        if ($expense->charges()->where('status', '!=', 'pending')->exists()) {
            throw new HttpApiException(
                'Esta cobrança já possui movimentações e não pode ser excluída.',
                'EXPENSE_CANNOT_BE_DELETED',
                422,
            );
        }

        DB::transaction(function () use ($expense) {
            foreach ($expense->charges as $charge) {
                $charge->paymentProofs()->delete();
                $charge->delete();
            }
            $expense->delete();
        });
    }

    /**
     * Reparte o total da despesa em centavos entre todas as cobranças (ordem por id).
     * A soma dos amounts coincide com total_amount; centavos restantes vão às primeiras cobranças.
     */
    public function redistributeChargeAmounts(Expense $expense): void
    {
        $charges = $expense->charges()->with('expenseParticipant')->orderBy('id')->get();
        $count = $charges->count();
        if ($count === 0) {
            return;
        }

        ChargeStatusTransition::assertAllPendingForRedistribution($charges);

        $totalCents = (int) round((float) $expense->total_amount * 100);
        $baseCents = intdiv($totalCents, $count);
        $remainder = $totalCents % $count;

        foreach ($charges as $index => $charge) {
            $cents = $baseCents + ($index < $remainder ? 1 : 0);
            $amount = round($cents / 100, 2);

            $charge->update([
                'amount' => $amount,
                'description' => $expense->description,
                'due_date' => $expense->due_date,
            ]);
            $charge->expenseParticipant?->update(['amount' => $amount]);
        }

        $expense->update(['amount_per_member' => round($baseCents / 100, 2)]);
    }

    /**
     * Atualiza snapshot do participante (nome/telefone e opcionalmente valor da cobrança).
     * Exige todas as charges da despesa em `pending`.
     *
     * @param  array{name?: string, phone?: string, amount?: float|int|string|null}  $data
     */
    public function updateExpenseParticipant(ExpenseParticipant $participant, array $data): Expense
    {
        $expense = $participant->expense;
        if ($expense->status === 'closed') {
            throw new \DomainException('Esta despesa foi finalizada e nao aceita mais alteracoes.');
        }

        $charges = $expense->charges;
        ChargeStatusTransition::assertAllPendingForRedistribution($charges);

        $charge = Charge::query()
            ->where('expense_participant_id', $participant->id)
            ->firstOrFail();

        return DB::transaction(function () use ($participant, $charge, $expense, $data) {
            $name = array_key_exists('name', $data)
                ? trim((string) $data['name'])
                : $participant->name;
            $phoneDigits = array_key_exists('phone', $data)
                ? PhoneNormalizer::digits((string) $data['phone'])
                : (string) $participant->phone_normalized;

            if ($name === '' || strlen($phoneDigits) < 10) {
                throw new \DomainException('Informe nome e telefone válidos.');
            }

            if ($phoneDigits !== (string) $participant->phone_normalized) {
                $dup = $expense->charges()->whereHas('expenseParticipant', function ($q) use ($participant, $phoneDigits): void {
                    $q->where('phone_normalized', $phoneDigits)
                        ->where('id', '!=', $participant->id);
                })->exists();
                if ($dup) {
                    throw new \DomainException('Já existe participante com este telefone nesta cobrança.');
                }
            }

            $participant->update([
                'name' => $name,
                'phone' => $phoneDigits,
                'phone_normalized' => $phoneDigits,
            ]);

            if (array_key_exists('amount', $data) && $data['amount'] !== null) {
                $newAmt = round((float) $data['amount'], 2);
                if ($newAmt <= 0) {
                    throw new \DomainException('O valor do participante deve ser maior que zero.');
                }
                $charge->update([
                    'amount' => $newAmt,
                    'description' => $expense->description,
                    'due_date' => $expense->due_date,
                ]);
                $participant->update(['amount' => $newAmt]);

                $sum = round((float) $expense->charges()->sum('amount'), 2);
                $total = round((float) $expense->total_amount, 2);
                if (abs($sum - $total) > 0.02) {
                    throw new \DomainException('A soma dos valores dos participantes deve ser igual ao valor total da cobrança.');
                }

                $count = $expense->charges()->count();
                $avg = $count > 0 ? round($total / $count, 2) : 0;
                $expense->update(['amount_per_member' => $avg]);
            }

            return $expense->fresh()->load(['charges.expenseParticipant', 'charges.teamMember']);
        });
    }

    public function deleteExpenseParticipant(ExpenseParticipant $participant): Expense
    {
        $expense = $participant->expense;
        if ($expense->status === 'closed') {
            throw new \DomainException('Esta despesa foi finalizada e nao aceita mais alteracoes.');
        }

        $charges = $expense->charges;
        ChargeStatusTransition::assertAllPendingForRedistribution($charges);

        if ($charges->count() <= 1) {
            throw new \DomainException('Não é possível remover o único participante. Exclua a cobrança ou adicione outro participante antes.');
        }

        $charge = Charge::query()
            ->where('expense_participant_id', $participant->id)
            ->firstOrFail();

        DB::transaction(function () use ($participant, $charge) {
            $charge->paymentProofs()->delete();
            $charge->delete();
            $participant->delete();
        });

        $reloaded = Expense::query()->findOrFail($expense->id);
        $this->redistributeChargeAmounts($reloaded);

        return $reloaded->fresh()->load(['charges.expenseParticipant', 'charges.teamMember']);
    }
}
