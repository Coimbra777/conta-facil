<?php

namespace App\Actions\Expense;

use App\Models\Expense;
use App\Models\Team;
use App\Models\User;
use App\Services\ExpenseService;

class CreateExpenseAction
{
    public function __construct(private ExpenseService $expenseService) {}

    public function execute(Team $team, User $creator, array $data): Expense
    {
        return $this->expenseService->createExpenseAndSplit($team, $creator, $data);
    }
}
