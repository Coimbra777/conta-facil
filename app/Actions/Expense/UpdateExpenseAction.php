<?php

namespace App\Actions\Expense;

use App\Models\Expense;
use App\Services\ExpenseService;

class UpdateExpenseAction
{
    public function __construct(private ExpenseService $expenseService) {}

    public function execute(Expense $expense, array $data): Expense
    {
        return $this->expenseService->updateExpense($expense, $data);
    }
}
