<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Expense;
use App\Support\ExpenseAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class ShowExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();
        /** @var Expense|null $expense */
        $expense = $this->route('expense');

        return $user && $expense instanceof Expense
            && ExpenseAuthorizer::canManage($user, $expense);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
