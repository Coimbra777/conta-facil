<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\NormalizesParticipantFormInput;
use App\Rules\BrazilPhone;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class StorePublicExpenseRequest extends FormRequest
{
    use NormalizesParticipantFormInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_phone' => ['required', 'string', 'max:32', new BrazilPhone()],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'pix_key' => ['required', 'string', 'max:255'],
            'pix_qr_code' => ['nullable', 'string'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'include_owner_as_participant' => ['sometimes', 'boolean'],
            'participants' => ['sometimes', 'array'],
            'participants.*.name' => ['required_with:participants.*.phone', 'string', 'max:255'],
            'participants.*.phone' => ['required_with:participants.*.name', 'string', 'max:32', new BrazilPhone()],
            'participants_text' => ['nullable', 'string', 'max:20000'],
        ];
    }

    public function messages(): array
    {
        return [
            'owner_name.required' => 'Informe o nome do responsável pela cobrança.',
            'owner_phone.required' => 'Informe o telefone do responsável pela cobrança.',
            'description.required' => 'Informe a descrição da cobrança.',
            'description.string' => 'Informe uma descrição válida para a cobrança.',
            'description.max' => 'A descrição da cobrança é muito longa.',
            'amount.required' => 'Informe o valor total da cobrança.',
            'amount.numeric' => 'Informe um valor total válido.',
            'amount.min' => 'O valor total deve ser maior que zero.',
            'pix_key.required' => 'Informe a chave Pix.',
            'pix_key.string' => 'Informe uma chave Pix válida.',
            'pix_key.max' => 'A chave Pix informada é muito longa.',
            'pix_qr_code.string' => 'Informe um QR Code Pix válido.',
            'due_date.required' => 'Informe a data de vencimento.',
            'due_date.date' => 'Informe uma data de vencimento válida.',
            'due_date.after_or_equal' => 'Informe uma data de vencimento igual ou posterior a hoje.',
            'participants.*.name.required_with' => 'Informe o nome do participante.',
            'participants.*.phone.required_with' => 'Informe o telefone do participante.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('owner_phone')) {
            $this->merge([
                'owner_phone' => PhoneNormalizer::digits($this->input('owner_phone')),
            ]);
        }

        $merged = $this->buildNormalizedParticipantsList(
            $this->input('participants'),
            (string) $this->input('participants_text', ''),
        );

        $seen = [];
        foreach ($merged as $item) {
            $seen[$item['phone']] = true;
        }

        if ($this->boolean('include_owner_as_participant')) {
            $ownerPhone = PhoneNormalizer::digits((string) $this->input('owner_phone'));
            $ownerName = trim((string) $this->input('owner_name'));
            if ($ownerName !== '' && PhoneNormalizer::isValid($ownerPhone) && ! isset($seen[$ownerPhone])) {
                array_unshift($merged, ['name' => $ownerName, 'phone' => $ownerPhone]);
                $seen[$ownerPhone] = true;
            }
        }

        $this->merge(['participants' => $merged]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (count($this->input('participants', [])) < 1) {
                $validator->errors()->add('participants', 'Adicione pelo menos um participante.');
            }
        });
    }
}
