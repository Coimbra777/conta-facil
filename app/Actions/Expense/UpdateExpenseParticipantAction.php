<?php

namespace App\Actions\Expense;

use App\Models\ExpenseParticipant;
use App\Services\ExpenseService;

class UpdateExpenseParticipantAction
{
    public function __construct(private ExpenseService $expenseService) {}

    /**
     * @param  array{name?: string, phone?: string, amount?: float|int|string|null}  $data
     */
    public function execute(ExpenseParticipant $participant, array $data): Expense
    {
        return $this->expenseService->updateExpenseParticipant($participant, $data);
    }
}
