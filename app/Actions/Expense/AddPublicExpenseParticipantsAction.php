<?php

namespace App\Actions\Expense;

use App\Exceptions\HttpApiException;
use App\Models\Charge;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Services\ExpenseService;
use App\Support\PhoneNormalizer;
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
            ->with(Charge::EAGER_WITH_PARTICIPANT)
            ->get()
            ->map(fn (Charge $c) => PhoneNormalizer::digits(
                (string) ($c->participantIdentity()['phone'] ?? '')
            ))
            ->filter()
            ->all();

        foreach ($participants as $p) {
            $digits = PhoneNormalizer::digits((string) ($p['phone'] ?? ''));
            if ($digits !== '' && in_array($digits, $existingPhones, true)) {
                throw new HttpApiException(
                    'Participante com este telefone ja existe nesta despesa.',
                    'DUPLICATE_PARTICIPANT',
                    422,
                );
            }
        }

        return DB::transaction(function () use ($expense, $participants) {
            foreach ($participants as $p) {
                $digits = PhoneNormalizer::digits((string) ($p['phone'] ?? ''));

                $expenseParticipant = ExpenseParticipant::create([
                    'expense_id' => $expense->id,
                    'name' => trim((string) ($p['name'] ?? '')),
                    'phone' => $p['phone'],
                    'phone_normalized' => $digits !== '' ? $digits : null,
                    'email' => null,
                    'amount' => 0,
                    'metadata' => null,
                ]);

                Charge::create([
                    'expense_participant_id' => $expenseParticipant->id,
                    'expense_id' => $expense->id,
                    'description' => $expense->description,
                    'amount' => 0.0,
                    'due_date' => $expense->due_date,
                    'status' => 'pending',
                ]);
            }

            $expense->refresh();

            $this->expenseService->redistributeChargeAmounts($expense);

            return $expense->fresh()->load(Charge::eagerChargesWithParticipant());
        });
    }
}
