<?php

namespace App\Actions\Expense;

use App\Exceptions\HttpApiException;
use App\Models\Charge;
use App\Models\Expense;
use App\Models\TeamMember;
use App\Services\ExpenseService;
use Illuminate\Support\Facades\DB;

class AddPublicExpenseParticipantsAction
{
    public function __construct(private ExpenseService $expenseService) {}

    /**
     * @param  list<array{name: string, phone: string}>  $participants
     */
    public function execute(Expense $expense, array $participants): Expense
    {
        if ($expense->charges()->where('status', '!=', 'pending')->exists()) {
            throw new HttpApiException(
                'Não é possível redistribuir valores pois já existem pagamentos em andamento.',
                'INVALID_EXPENSE_STATE',
                422,
            );
        }

        $existingPhones = $expense->charges()
            ->with('teamMember')
            ->get()
            ->map(fn (Charge $c) => preg_replace('/\D+/', '', (string) ($c->teamMember?->phone ?? '')))
            ->filter()
            ->all();

        foreach ($participants as $p) {
            $digits = $p['phone'];
            if (in_array($digits, $existingPhones, true)) {
                throw new HttpApiException(
                    'Participante com este telefone ja existe nesta despesa.',
                    'DUPLICATE_PARTICIPANT',
                    422,
                );
            }
        }

        return DB::transaction(function () use ($expense, $participants) {
            foreach ($participants as $p) {
                $member = TeamMember::create([
                    'team_id' => $expense->team_id,
                    'user_id' => null,
                    'name' => $p['name'],
                    'phone' => $p['phone'],
                    'role' => 'member',
                ]);

                Charge::create([
                    'team_member_id' => $member->id,
                    'expense_id' => $expense->id,
                    'description' => $expense->description,
                    'amount' => 0.0,
                    'due_date' => $expense->due_date,
                    'status' => 'pending',
                ]);
            }

            $expense->refresh();

            $this->expenseService->redistributeChargeAmounts($expense);

            return $expense->fresh()->load(['charges.teamMember']);
        });
    }
}
