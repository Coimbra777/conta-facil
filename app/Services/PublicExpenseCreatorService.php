<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use Illuminate\Support\Facades\DB;

class PublicExpenseCreatorService
{
    public function __construct(private ExpenseService $expenseService) {}

    /**
     * @param  array{owner_name: string, owner_phone: string, description: string, amount: float|int|string, pix_key: string, pix_qr_code: ?string, due_date: string, participants: list<array{name: string, phone: string}>}  $data
     */
    public function create(array $data): Expense
    {
        return DB::transaction(function () use ($data) {
            $totalAmount = (float) $data['amount'];

            $expense = Expense::create([
                'created_by' => null,
                'owner_name' => $data['owner_name'],
                'owner_phone' => $data['owner_phone'],
                'description' => $data['description'],
                'total_amount' => $totalAmount,
                'amount_per_participant' => 0,
                'due_date' => $data['due_date'],
                'pix_key' => $data['pix_key'],
                'pix_qr_code' => $data['pix_qr_code'] ?? null,
                'status' => 'open',
            ]);

            foreach ($data['participants'] as $participant) {
                $digits = preg_replace('/\D+/', '', (string) ($participant['phone'] ?? '')) ?? '';

                $expenseParticipant = ExpenseParticipant::create([
                    'expense_id' => $expense->id,
                    'name' => trim((string) ($participant['name'] ?? '')),
                    'phone' => $participant['phone'],
                    'phone_normalized' => $digits !== '' ? $digits : null,
                    'email' => null,
                    'amount' => 0,
                    'metadata' => null,
                ]);

                Charge::create([
                    'expense_participant_id' => $expenseParticipant->id,
                    'expense_id' => $expense->id,
                    'description' => $data['description'],
                    'amount' => 0.0,
                    'due_date' => $data['due_date'],
                    'status' => 'pending',
                ]);
            }

            $this->expenseService->redistributeChargeAmounts($expense);

            return $expense->load(Charge::eagerChargesWithParticipant());
        });
    }
}
