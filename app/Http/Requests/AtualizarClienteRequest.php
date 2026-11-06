<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AtualizarClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $dados = [];

        if ($this->has('email') && is_string($this->input('email'))) {
            $dados['email'] = Str::lower(trim($this->input('email')));
        }

        if ($this->has('telefone') && is_string($this->input('telefone'))) {
            $dados['telefone'] = preg_replace('/\D/', '', $this->input('telefone'));
        }

        $this->merge($dados);
    }

    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'email' => [
                'sometimes', 'required', 'email', 'max:255',
                Rule::unique('cliente', 'email')->ignore($this->user()->id),
            ],
            'telefone' => ['sometimes', 'required', 'digits_between:10,11'],
            'senha' => ['nullable', 'string', 'min:8', 'max:72'],
        ];
    }
    
    public function alteraCredenciais(): bool
    {
        /** @var Cliente $cliente */
        $cliente = $this->user();

        $trocaEmail = $this->filled('email') && $this->input('email') !== Str::lower($cliente->email);

        return $this->filled('senha') || $trocaEmail;
    }

    public function attributes(): array
    {
        return [
            'senha' => 'nova senha',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este e-mail já está cadastrado.',
            'telefone.digits_between' => 'Informe o telefone com DDD (10 ou 11 dígitos).',
            'senha.min' => 'A nova senha deve ter pelo menos 8 caracteres.',
        ];
    }
}
