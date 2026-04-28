<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\ChargeStatusTransition;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExpenseService
{
    public function __construct(private NotificationService $notificationService) {}

    public function createExpenseAndSplit(Team $team, User $creator, array $data): Expense
    {
        $members = $team->members()->get();

        if ($members->isEmpty()) {
            throw new \DomainException('Team has no members.');
        }

        $expense = DB::transaction(function () use ($team, $creator, $data, $members) {
            $expense = Expense::create([
                'team_id' => $team->id,
                'created_by' => $creator->id,
                'description' => $data['description'],
                'total_amount' => $data['total_amount'],
                'amount_per_member' => 0,
                'due_date' => $data['due_date'],
                'pix_key' => $data['pix_key'],
                'pix_qr_code' => $data['pix_qr_code'] ?? null,
                'status' => 'open',
                'public_hash' => (string) Str::uuid(),
                'manage_token' => (string) Str::uuid(),
            ]);

            foreach ($members as $member) {
                Charge::create([
                    'team_member_id' => $member->id,
                    'user_id' => $member->user_id,
                    'expense_id' => $expense->id,
                    'description' => $data['description'],
                    'amount' => 0.0,
                    'due_date' => $data['due_date'],
                    'status' => 'pending',
                ]);
            }

            $this->redistributeChargeAmounts($expense->fresh());

            return $expense->fresh()->load('charges.teamMember');
        });

        foreach ($members as $member) {
            $charge = $expense->charges()->where('team_member_id', $member->id)->first();
            if (! $charge || ! $charge->teamMember) {
                continue;
            }

            try {
                $this->notificationService->sendChargeNotification(
                    $member,
                    $charge->fresh(),
                    $expense->fresh()
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to notify charge', [
                    'team_member_id' => $member->id,
                    'charge_id' => $charge->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $expense;
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

            return $expense->fresh()->load('charges.teamMember');
        });
    }

    /**
     * @param  list<array{name: string, phone: string}>  $participants  phones apenas digitos
     */
    public function addParticipantsToExpense(Team $team, Expense $expense, array $participants): Expense
    {
        if ($expense->status === 'closed') {
            throw new \DomainException('Esta despesa foi finalizada e nao aceita mais alteracoes.');
        }

        if ($expense->team_id !== $team->id) {
            throw new \DomainException('Despesa nao pertence a esta equipe.');
        }

        if ($expense->charges()->where('status', '!=', 'pending')->exists()) {
            throw new \DomainException(
                'Não é possível redistribuir valores pois já existem pagamentos em andamento.'
            );
        }

        $result = DB::transaction(function () use ($team, $expense, $participants) {
            $newChargeIds = [];

            foreach ($participants as $p) {
                $phone = PhoneNormalizer::digits($p['phone'] ?? '');
                $name = trim((string) ($p['name'] ?? ''));
                if ($phone === '' || strlen($phone) < 10 || $name === '') {
                    continue;
                }

                $member = TeamMember::where('team_id', $team->id)
                    ->get()
                    ->first(fn (TeamMember $m) => PhoneNormalizer::digits((string) $m->phone) === $phone);

                if (! $member) {
                    $member = TeamMember::create([
                        'team_id' => $team->id,
                        'name' => $name,
                        'phone' => $phone,
                        'role' => 'member',
                    ]);
                }

                if ($expense->charges()->where('team_member_id', $member->id)->exists()) {
                    continue;
                }

                $charge = Charge::create([
                    'team_member_id' => $member->id,
                    'user_id' => $member->user_id,
                    'expense_id' => $expense->id,
                    'description' => $expense->description,
                    'amount' => 0.0,
                    'due_date' => $expense->due_date,
                    'status' => 'pending',
                ]);
                $newChargeIds[] = $charge->id;
            }

            if ($newChargeIds === []) {
                throw new \DomainException(
                    'Nenhum participante novo: verifique os telefones ou se ja estao na despesa.'
                );
            }

            $this->redistributeChargeAmounts($expense->fresh());

            return [
                'expense' => $expense->fresh()->load('charges.teamMember'),
                'newChargeIds' => $newChargeIds,
            ];
        });

        foreach ($result['newChargeIds'] as $chargeId) {
            $charge = Charge::query()->with('teamMember')->find($chargeId);
            if ($charge && $charge->teamMember) {
                try {
                    $this->notificationService->sendChargeNotification(
                        $charge->teamMember,
                        $charge->fresh(),
                        $result['expense']->fresh()
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
     * Reparte o total da despesa em centavos entre todas as cobranças (ordem por id).
     * A soma dos amounts coincide com total_amount; centavos restantes vão às primeiras cobranças.
     */
    public function redistributeChargeAmounts(Expense $expense): void
    {
        $charges = $expense->charges()->orderBy('id')->get();
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
        }

        $expense->update(['amount_per_member' => round($baseCents / 100, 2)]);
    }
}
