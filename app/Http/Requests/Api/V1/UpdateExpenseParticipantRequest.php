<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Support\ExpenseAuthorizer;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();
        /** @var Expense|null $expense */
        $expense = $this->route('expense');
        /** @var ExpenseParticipant|null $participant */
        $participant = $this->route('participant');
        if (! $user || ! $expense instanceof Expense || ! $participant instanceof ExpenseParticipant) {
            return false;
        }
        if ((int) $participant->expense_id !== (int) $expense->id) {
            return false;
        }

        return ExpenseAuthorizer::canManage($user, $expense);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:32'],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $digits = PhoneNormalizer::digits((string) $this->input('phone'));
            $this->merge(['phone' => $digits]);
        }
    }
}
