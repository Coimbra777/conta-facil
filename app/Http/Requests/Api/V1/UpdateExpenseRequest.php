<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Expense;
use App\Support\ExpenseAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();
        if (! $user) {
            return false;
        }

        /** @var Expense|null $expense */
        $expense = $this->route('expense');
        if (! $expense instanceof Expense) {
            return false;
        }

        return ExpenseAuthorizer::canManage($user, $expense);
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:5'],
            'due_date' => ['required', 'date'],
            'pix_key' => ['required', 'string', 'max:255'],
            'pix_qr_code' => ['nullable', 'string'],
        ];
    }
}
