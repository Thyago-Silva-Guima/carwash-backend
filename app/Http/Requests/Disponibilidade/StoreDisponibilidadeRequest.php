<?php

namespace App\Http\Requests\Disponibilidade;

use App\Services\Agendamento\DisponibilidadeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDisponibilidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // acesso já restrito pelo middleware "admin"
    }

    public function rules(): array
    {
        return [
            'data' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fim' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'capacidade' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $sobrepoe = app(DisponibilidadeService::class)->existeSobreposicao(
                $this->input('data'), $this->input('hora_inicio'), $this->input('hora_fim')
            );

            if ($sobrepoe) {
                $v->errors()->add('hora_inicio', 'Já existe uma janela cadastrada neste horário.');
            }
        });
    }
}
