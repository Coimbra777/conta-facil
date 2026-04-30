<?php

namespace App\Actions\Expense;

use App\Models\ExpenseParticipant;
use App\Services\ExpenseService;

class DeleteExpenseParticipantAction
{
    public function __construct(private ExpenseService $expenseService) {}

    public function execute(ExpenseParticipant $participant): Expense
    {
        return $this->expenseService->deleteExpenseParticipant($participant);
    }
}
