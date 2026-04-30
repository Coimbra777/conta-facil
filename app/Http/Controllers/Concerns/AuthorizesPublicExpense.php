<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\HttpApiException;
use App\Models\Expense;
use App\Support\ExpenseClosedPolicy;
use App\Support\ManageTokenResolver;
use Illuminate\Http\Request;

trait AuthorizesPublicExpense
{
    /**
     * @throws HttpApiException
     */
    protected function authorizeManage(Request $request, ?Expense $expense): Expense
    {
        if (! $expense || ! $expense->public_hash) {
            throw new HttpApiException('Registro não encontrado.', 'NOT_FOUND', 404);
        }

        return $this->authorizeManageToken($request, $expense);
    }

    /**
     * @throws HttpApiException
     */
    protected function authorizeManageToken(Request $request, Expense $expense): Expense
    {
        $token = ManageTokenResolver::resolve($request);
        if (! $token || ! hash_equals((string) $expense->manage_token, (string) $token)) {
            throw new HttpApiException(
                'Token de gestão inválido.',
                'INVALID_MANAGE_TOKEN',
                403,
            );
        }

        return $expense;
    }

    /**
     * @throws HttpApiException
     */
    protected function assertExpenseOpen(Expense $expense): void
    {
        ExpenseClosedPolicy::assertOpen($expense);
    }

}
