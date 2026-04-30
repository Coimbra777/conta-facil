<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Support\ExpenseAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class DestroyExpenseParticipantRequest extends FormRequest
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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
