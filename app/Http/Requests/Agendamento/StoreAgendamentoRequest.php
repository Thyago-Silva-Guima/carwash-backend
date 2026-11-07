<?php

namespace App\Http\Requests\Agendamento;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgendamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'endereco_id' => ['required', 'integer',
                Rule::exists('endereco', 'id')->where('cliente_id', $this->user()->id)],
            'data_hora' => ['required', 'date'],
            'servicos' => ['required', 'array', 'min:1'],
            'servicos.*.servico_id' => ['required', 'integer', 'distinct',
                Rule::exists('servico', 'id')->where('ativo', true)],
            'servicos.*.quantidade' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'observacoes' => ['nullable', 'string', 'max:500'],
            'anexos' => ['sometimes', 'array', 'max:5'],
            'anexos.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }
}
