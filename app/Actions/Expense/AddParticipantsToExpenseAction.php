<?php

namespace App\Actions\Expense;

use App\Models\Expense;
use App\Models\Team;
use App\Services\ExpenseService;

class AddParticipantsToExpenseAction
{
    public function __construct(private ExpenseService $expenseService) {}

    public function execute(Team $team, Expense $expense, array $participants): Expense
    {
        return $this->expenseService->addParticipantsToExpense($team, $expense, $participants);
    }
}
