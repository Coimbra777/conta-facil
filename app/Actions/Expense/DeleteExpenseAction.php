<?php

namespace App\Actions\Expense;

use App\Models\Expense;
use App\Services\ExpenseService;

class DeleteExpenseAction
{
    public function __construct(private ExpenseService $expenseService) {}

    public function execute(Expense $expense): void
    {
        $this->expenseService->deleteExpenseIfAllowed($expense);
    }
}
