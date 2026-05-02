<?php

namespace App\Http\Requests\Api\V1;

use App\Exceptions\HttpApiException;
use App\Models\Expense;
use App\Rules\BrazilPhone;
use App\Support\ExpenseAuthorizer;
use App\Support\ParticipantPhoneUniqueness;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class AddExpenseParticipantsRequest extends FormRequest
{
    private const PARTICIPANT_TOTAL_BELOW_EXPENSE_TOTAL_MESSAGE =
        'Os valores dos participantes ainda não fecham o total da cobrança.';

    private const PARTICIPANT_TOTAL_BELOW_EXPENSE_TOTAL_CODE =
        'PARTICIPANT_TOTAL_BELOW_EXPENSE_TOTAL';

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
            'participants.*.phone' => ['required', 'string', 'max:32', new BrazilPhone()],
            'participants.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'participants.required' => 'Adicione pelo menos um participante.',
            'participants.array' => 'Adicione pelo menos um participante.',
            'participants.min' => 'Adicione pelo menos um participante.',
            'participants.*.name.required' => 'Informe o nome do participante.',
            'participants.*.name.string' => 'Informe um nome válido para o participante.',
            'participants.*.name.max' => 'O nome do participante é muito longo.',
            'participants.*.phone.required' => 'Informe o telefone do participante.',
            'participants.*.phone.string' => 'Informe um telefone válido para o participante.',
            'participants.*.phone.max' => 'O telefone do participante é muito longo.',
            'participants.*.amount.required' => 'Informe o valor do participante.',
            'participants.*.amount.numeric' => 'Informe um valor válido para o participante.',
            'participants.*.amount.min' => 'O valor do participante deve ser maior que zero.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach ($this->input('participants', []) as $p) {
            if (! is_array($p)) {
                continue;
            }
            $normalized[] = [
                'name' => trim((string) ($p['name'] ?? '')),
                'phone' => PhoneNormalizer::digits($p['phone'] ?? ''),
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
                    'Adicione pelo menos um participante.'
                );

                return;
            }

            /** @var Expense|null $expense */
            $expense = $this->route('expense');
            if (! $expense instanceof Expense) {
                return;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $seenInPayload = [];
            foreach ($this->input('participants', []) as $p) {
                if (! is_array($p)) {
                    continue;
                }
                $phone = PhoneNormalizer::digits($p['phone'] ?? '');
                $name = trim((string) ($p['name'] ?? ''));
                if ($name === '' || ! PhoneNormalizer::isValid($phone)) {
                    continue;
                }
                if (isset($seenInPayload[$phone])) {
                    throw new HttpApiException(
                        ParticipantPhoneUniqueness::MESSAGE,
                        ParticipantPhoneUniqueness::CODE,
                        422,
                    );
                }
                $seenInPayload[$phone] = true;

                if ($expense->charges()->whereHas('expenseParticipant', function ($q) use ($phone): void {
                    $q->where('phone_normalized', $phone);
                })->exists()) {
                    throw new HttpApiException(
                        ParticipantPhoneUniqueness::MESSAGE,
                        ParticipantPhoneUniqueness::CODE,
                        422,
                    );
                }
            }

            $existingSum = round((float) $expense->charges()->sum('amount'), 2);
            $newSum = round(collect($this->input('participants', []))->sum(fn ($p) => (float) ($p['amount'] ?? 0)), 2);
            $total = round((float) $expense->total_amount, 2);
            if (($existingSum + $newSum) + 0.02 < $total) {
                throw new HttpApiException(
                    self::PARTICIPANT_TOTAL_BELOW_EXPENSE_TOTAL_MESSAGE,
                    self::PARTICIPANT_TOTAL_BELOW_EXPENSE_TOTAL_CODE,
                    422,
                );
            }
        });
    }
}
