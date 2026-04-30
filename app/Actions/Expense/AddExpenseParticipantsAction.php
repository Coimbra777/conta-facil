<?php

namespace App\Actions\Expense;

use App\Models\Expense;
use App\Services\ExpenseService;

class AddExpenseParticipantsAction
{
    public function __construct(private ExpenseService $expenseService) {}

    /**
     * @param  list<array{name: string, phone: string, amount?: float}>  $participants
     */
    public function execute(Expense $expense, array $participants): Expense
    {
        return $this->expenseService->addParticipantsToExpense($expense, $participants);
    }
}
