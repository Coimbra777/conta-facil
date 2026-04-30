<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Expense;
use App\Support\ExpenseAuthorizer;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class AddExpenseParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();
        /** @var Expense|null $expense */
        $expense = $this->route('expense');
        if (! $user || ! $expense instanceof Expense) {
            return false;
        }

        return ExpenseAuthorizer::canManage($user, $expense);
    }

    public function rules(): array
    {
        return [
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.name' => ['required', 'string', 'max:255'],
            'participants.*.phone' => ['required', 'string', 'max:32'],
            'participants.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach ($this->input('participants', []) as $p) {
            if (! is_array($p)) {
                continue;
            }
            $phone = PhoneNormalizer::digits($p['phone'] ?? '');
            $name = trim((string) ($p['name'] ?? ''));
            if ($phone === '' || strlen($phone) < 10 || $name === '') {
                continue;
            }
            $normalized[] = [
                'name' => $name,
                'phone' => $phone,
                'amount' => isset($p['amount']) ? round((float) $p['amount'], 2) : null,
            ];
        }
        $this->merge(['participants' => $normalized]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (count($this->input('participants', [])) < 1) {
                $validator->errors()->add(
                    'participants',
                    'Informe ao menos um participante com nome, telefone e valor válidos.'
                );

                return;
            }

            /** @var Expense|null $expense */
            $expense = $this->route('expense');
            if (! $expense instanceof Expense) {
                return;
            }

            $sum = round(collect($this->input('participants', []))->sum(fn ($p) => (float) ($p['amount'] ?? 0)), 2);
            $total = round((float) $expense->total_amount, 2);
            if (abs($sum - $total) > 0.02) {
                $validator->errors()->add(
                    'participants',
                    'A soma dos valores dos participantes deve ser igual ao valor total da cobrança.'
                );
            }
        });
    }
}
