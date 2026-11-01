<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegistrarClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $telefone = $this->input('telefone');

        $this->merge([
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
            'telefone' => is_string($telefone) ? preg_replace('/\D/', '', $telefone) : $telefone,
        ]);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'min:2', 'max:100'],
            'telefone' => ['required', 'digits_between:10,11'],
            'email' => ['required', 'email', 'max:255', Rule::unique('cliente', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:72'],
            'aceite_termos' => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'password' => 'senha',
            'aceite_termos' => 'aceite dos termos',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este e-mail já está cadastrado.',
            'telefone.digits_between' => 'Informe o telefone com DDD (10 ou 11 dígitos).',
            'aceite_termos.accepted' => 'É necessário aceitar os termos de uso.',
        ];
    }
}
