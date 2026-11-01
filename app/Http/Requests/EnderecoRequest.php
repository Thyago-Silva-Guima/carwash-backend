<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnderecoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $cep = $this->input('cep');
        $numero = $this->input('numero');

        $this->merge([
            'cep' => is_string($cep) ? preg_replace('/\D/', '', $cep) : $cep,
            'numero' => is_int($numero) ? (string) $numero : $numero,
        ]);
    }

    public function rules(): array
    {
        return [
            'rua' => ['required', 'string', 'min:2', 'max:150'],
            'numero' => ['required', 'string', 'max:20'],
            'bairro' => ['required', 'string', 'max:100'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'cidade' => ['required', 'string', 'max:100'],
            'cep' => ['required', 'digits:8'],
        ];
    }

    public function attributes(): array
    {
        return [
            'numero' => 'número',
            'cep' => 'CEP',
        ];
    }

    public function messages(): array
    {
        return [
            'cep.digits' => 'Informe o CEP com 8 dígitos.',
        ];
    }
}
