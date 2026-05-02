<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePublicExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'pix_key' => ['required', 'string', 'max:255'],
            'pix_qr_code' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'Informe a descrição da cobrança.',
            'description.string' => 'Informe uma descrição válida para a cobrança.',
            'description.max' => 'A descrição da cobrança é muito longa.',
            'amount.required' => 'Informe o valor total da cobrança.',
            'amount.numeric' => 'Informe um valor total válido.',
            'amount.min' => 'O valor total deve ser maior que zero.',
            'due_date.required' => 'Informe a data de vencimento.',
            'due_date.date' => 'Informe uma data de vencimento válida.',
            'due_date.after_or_equal' => 'Informe uma data de vencimento igual ou posterior a hoje.',
            'pix_key.required' => 'Informe a chave Pix.',
            'pix_key.string' => 'Informe uma chave Pix válida.',
            'pix_key.max' => 'A chave Pix informada é muito longa.',
            'pix_qr_code.string' => 'Informe um QR Code Pix válido.',
        ];
    }
}
