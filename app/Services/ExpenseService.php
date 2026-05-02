<?php

namespace App\Services;

use App\Exceptions\HttpApiException;
use App\Models\Charge;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\User;
use App\Support\ChargeStatusTransition;
use App\Support\ExpenseClosedPolicy;
use App\Support\ParticipantPhoneUniqueness;
use App\Support\PhoneNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpenseService
{
    public const PARTICIPANT_TOTAL_BELOW_EXPENSE_TOTAL_MESSAGE =
        'Os valores dos participantes ainda não fecham o total da cobrança.';

    public const PARTICIPANT_TOTAL_BELOW_EXPENSE_TOTAL_CODE =
        'PARTICIPANT_TOTAL_BELOW_EXPENSE_TOTAL';

    public function __construct(
        private NotificationService $notificationService,
        private PaymentProofService $paymentProofService,
    ) {}

    /**
     * Cria cobrança sem participantes (charges). Valores por participante em addParticipantsToExpense.
     */
    public function createExpenseForUser(User $creator, array $data): Expense
    {
        return DB::transaction(function () use ($creator, $data) {
            $expense = Expense::create([
                'created_by' => $creator->id,
                'description' => $data['description'],
                'total_amount' => $data['total_amount'],
                'amount_per_participant' => 0,
                'due_date' => $data['due_date'],
                'pix_key' => $data['pix_key'],
                'pix_qr_code' => $data['pix_qr_code'] ?? null,
                'status' => 'open',
            ]);

            return $expense->fresh()->load(Charge::eagerChargesWithParticipant());
        });
    }

    public function updateExpense(Expense $expense, array $data): Expense
    {
        ExpenseClosedPolicy::assertOpen($expense);

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

            return $expense->fresh()->load(Charge::eagerChargesWithParticipant());
        });
    }

    /**
     * Insere apenas participantes novos (telefone ainda não presente na despesa).
     * A soma dos valores já gravados nas cobranças existentes mais os informados neste POST deve igualar o total da despesa.
     *
     * @param  list<array{name: string, phone: string, amount?: float|int|string|null}>  $participants
     */
    public function addParticipantsToExpense(Expense $expense, array $participants): Expense
    {
        ExpenseClosedPolicy::assertOpen($expense);

        if ($expense->charges()->where('status', '!=', 'pending')->exists()) {
            throw new \DomainException(
                'Não é possível redistribuir valores pois já existem pagamentos em andamento.'
            );
        }

        $this->assertNewParticipantPhonesAllowed($expense, $participants);

        try {
            $result = DB::transaction(function () use ($expense, $participants) {
                $newChargeIds = [];

                foreach ($participants as $p) {
                    $phone = PhoneNormalizer::digits($p['phone'] ?? '');
                    $name = trim((string) ($p['name'] ?? ''));
                    if ($name === '' || ! PhoneNormalizer::isValid($phone)) {
                        throw new \DomainException(
                            'Informe nome e telefone válidos para cada participante novo.'
                        );
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
                        'expense_participant_id' => $participant->id,
                        'expense_id' => $expense->id,
                        'description' => $expense->description,
                        'amount' => 0.0,
                        'due_date' => $expense->due_date,
                        'status' => 'pending',
                    ]);
                    $newChargeIds[] = $charge->id;
                }

                $fresh = $expense->fresh()->load(Charge::eagerChargesWithParticipant());
                $this->applyIncrementalParticipantAmounts($fresh, $participants);

                return [
                    'expense' => $fresh->load(Charge::eagerChargesWithParticipant()),
                    'newChargeIds' => $newChargeIds,
                ];
            });
        } catch (QueryException $e) {
            $this->throwDuplicateParticipantPhoneIfNeeded($e);

            throw $e;
        }

        foreach ($result['newChargeIds'] as $chargeId) {
            $charge = Charge::query()
                ->with(Charge::EAGER_WITH_PARTICIPANT)
                ->find($chargeId);
            if ($charge && $charge->participantPayload() !== null) {
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
     * Aplica apenas os valores enviados neste POST aos participantes novos; mantém valores já gravados nas cobranças existentes.
     * Exige round(sum(cobranças existentes) + sum(payload)) >= total_amount da despesa.
     *
     * @param  list<array{name: string, phone: string, amount?: float|int|string|null}>  $newParticipantsOnly
     */
    private function applyIncrementalParticipantAmounts(Expense $expense, array $newParticipantsOnly): void
    {
        $charges = $expense->charges()->with(Charge::EAGER_WITH_PARTICIPANT)->orderBy('id')->get();
        ChargeStatusTransition::assertAllPendingForRedistribution($charges);

        $amountByPhoneNew = [];
        foreach ($newParticipantsOnly as $p) {
            $phone = PhoneNormalizer::digits($p['phone'] ?? '');
            if ($phone === '') {
                continue;
            }
            $amount = isset($p['amount']) ? round((float) $p['amount'], 2) : null;
            if ($amount === null || $amount <= 0) {
                throw new \DomainException('Cada participante deve ter um valor maior que zero.');
            }
            $amountByPhoneNew[$phone] = $amount;
        }

        $expectedSum = round((float) $expense->total_amount, 2);
        $running = 0.0;
        foreach ($charges as $charge) {
            $row = $charge->participantIdentity();
            $participantPhone = PhoneNormalizer::digits((string) ($row['phone'] ?? ''));
            if ($participantPhone === '') {
                throw new \DomainException('Participante sem telefone válido.');
            }
            if (isset($amountByPhoneNew[$participantPhone])) {
                $running += $amountByPhoneNew[$participantPhone];
            } else {
                $running += round((float) $charge->amount, 2);
            }
        }
        $running = round($running, 2);
        if ($running + 0.02 < $expectedSum) {
            throw new HttpApiException(
                self::PARTICIPANT_TOTAL_BELOW_EXPENSE_TOTAL_MESSAGE,
                self::PARTICIPANT_TOTAL_BELOW_EXPENSE_TOTAL_CODE,
                422,
            );
        }

        foreach (array_keys($amountByPhoneNew) as $phoneKey) {
            // PHP casts numeric string keys to int; normalize before comparing to digits-only strings.
            $phone = PhoneNormalizer::digits((string) $phoneKey);
            $found = false;
            foreach ($charges as $charge) {
                $row = $charge->participantIdentity();
                if (PhoneNormalizer::digits((string) ($row['phone'] ?? '')) === $phone) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                throw new \DomainException('Inconsistência ao aplicar valores dos novos participantes.');
            }
        }

        foreach ($charges as $charge) {
            $row = $charge->participantIdentity();
            $participantPhone = PhoneNormalizer::digits((string) ($row['phone'] ?? ''));
            if (! isset($amountByPhoneNew[$participantPhone])) {
                continue;
            }
            $amt = $amountByPhoneNew[$participantPhone];
            $charge->update([
                'amount' => $amt,
                'description' => $expense->description,
                'due_date' => $expense->due_date,
            ]);
            $charge->expenseParticipant?->update(['amount' => $amt]);
        }

        $count = $charges->count();
        $avg = $count > 0 ? round($expectedSum / $count, 2) : 0;
        $expense->update(['amount_per_participant' => $avg]);
    }

    /**
     * Exclui despesa somente se todas as cobranças estiverem pendentes (sem comprovante/pagamento).
     */
    public function deleteExpenseIfAllowed(Expense $expense): void
    {
        ExpenseClosedPolicy::assertOpen($expense);

        if ($expense->charges()->where('status', '!=', 'pending')->exists()) {
            throw new HttpApiException(
                'Esta cobrança já possui movimentações e não pode ser excluída.',
                'EXPENSE_CANNOT_BE_DELETED',
                422,
            );
        }

        DB::transaction(function () use ($expense) {
            foreach ($expense->charges()->get() as $charge) {
                $this->paymentProofService->deleteProofsForCharge($charge);
                $charge->delete();
            }
            $expense->delete();
        });
    }

    public function closeExpense(Expense $expense): Expense
    {
        return DB::transaction(function () use ($expense) {
            /** @var Expense $lockedExpense */
            $lockedExpense = Expense::query()
                ->whereKey($expense->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedExpense->status !== 'closed') {
                $lockedExpense->update(['status' => 'closed']);
            }

            $this->paymentProofService->removeProofFilesForExpense($lockedExpense);

            return $lockedExpense->fresh()->load(Charge::eagerChargesWithParticipantAndProofs());
        });
    }

    public function closeExpenseWhenAllChargesValidated(Expense $expense): Expense
    {
        $expense->refresh();

        if (! $expense->charges()->exists()) {
            return $expense;
        }

        $allValidated = $expense->charges()
            ->where('status', '!=', 'validated')
            ->doesntExist();

        if (! $allValidated) {
            return $expense;
        }

        return $this->closeExpense($expense);
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

        $expense->update(['amount_per_participant' => round($baseCents / 100, 2)]);
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
        ExpenseClosedPolicy::assertOpen($expense);

        $charges = $expense->charges;
        ChargeStatusTransition::assertAllPendingForRedistribution($charges);

        $charge = Charge::query()
            ->where('expense_participant_id', $participant->id)
            ->firstOrFail();

        try {
            return DB::transaction(function () use ($participant, $charge, $expense, $data) {
                $name = array_key_exists('name', $data)
                    ? trim((string) $data['name'])
                    : $participant->name;
                $phoneDigits = array_key_exists('phone', $data)
                    ? PhoneNormalizer::digits((string) $data['phone'])
                    : (string) $participant->phone_normalized;

                if ($name === '' || ! PhoneNormalizer::isValid($phoneDigits)) {
                    throw new \DomainException('Informe nome e telefone válidos.');
                }

                if ($phoneDigits !== (string) $participant->phone_normalized) {
                    $dup = $expense->charges()->whereHas('expenseParticipant', function ($q) use ($participant, $phoneDigits): void {
                        $q->where('phone_normalized', $phoneDigits)
                            ->where('id', '!=', $participant->id);
                    })->exists();
                    if ($dup) {
                        throw ParticipantPhoneUniqueness::makeException();
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
                    if ($sum + 0.02 < $total) {
                        throw new HttpApiException(
                            self::PARTICIPANT_TOTAL_BELOW_EXPENSE_TOTAL_MESSAGE,
                            self::PARTICIPANT_TOTAL_BELOW_EXPENSE_TOTAL_CODE,
                            422,
                        );
                    }

                    $count = $expense->charges()->count();
                    $avg = $count > 0 ? round($total / $count, 2) : 0;
                    $expense->update(['amount_per_participant' => $avg]);
                }

                return $expense->fresh()->load(Charge::eagerChargesWithParticipant());
            });
        } catch (QueryException $e) {
            $this->throwDuplicateParticipantPhoneIfNeeded($e);

            throw $e;
        }
    }

    public function deleteExpenseParticipant(ExpenseParticipant $participant): Expense
    {
        $expense = $participant->expense;
        ExpenseClosedPolicy::assertOpen($expense);

        $charges = $expense->charges;
        ChargeStatusTransition::assertAllPendingForRedistribution($charges);

        if ($charges->count() <= 1) {
            throw new \DomainException('Não é possível remover o único participante. Exclua a cobrança ou adicione outro participante antes.');
        }

        $charge = Charge::query()
            ->where('expense_participant_id', $participant->id)
            ->firstOrFail();

        DB::transaction(function () use ($participant, $charge) {
            $this->paymentProofService->deleteProofsForCharge($charge);
            $charge->delete();
            $participant->delete();
        });

        $reloaded = Expense::query()->findOrFail($expense->id);
        $this->redistributeChargeAmounts($reloaded);

        return $reloaded->fresh()->load(Charge::eagerChargesWithParticipant());
    }

    /**
     * POST /participants só aceita telefones novos nesta despesa; duplicata no payload ou já cadastrado → 422.
     *
     * @param  list<array{name?: string, phone?: string, amount?: mixed}>  $participants
     */
    private function assertNewParticipantPhonesAllowed(Expense $expense, array $participants): void
    {
        $seenInPayload = [];

        foreach ($participants as $p) {
            $phone = PhoneNormalizer::digits($p['phone'] ?? '');
            $name = trim((string) ($p['name'] ?? ''));
            if ($name === '' || ! PhoneNormalizer::isValid($phone)) {
                continue;
            }

            if (isset($seenInPayload[$phone])) {
                throw new HttpApiException(
                    ParticipantPhoneUniqueness::MESSAGE,
                    ParticipantPhoneUniqueness::CODE,
                    422,
                );
            }
            $seenInPayload[$phone] = true;

            if ($expense->charges()->whereHas('expenseParticipant', function ($q) use ($phone): void {
                $q->where('phone_normalized', $phone);
            })->exists()) {
                throw new HttpApiException(
                    ParticipantPhoneUniqueness::MESSAGE,
                    ParticipantPhoneUniqueness::CODE,
                    422,
                );
            }
        }
    }

    private function throwDuplicateParticipantPhoneIfNeeded(QueryException $e): void
    {
        if (ParticipantPhoneUniqueness::matchesQueryException($e)) {
            throw ParticipantPhoneUniqueness::makeException();
        }
    }
}
