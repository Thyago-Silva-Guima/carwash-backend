<?php

namespace App\Http\Requests\Disponibilidade;

use App\Services\Agendamento\DisponibilidadeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDisponibilidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hora_inicio' => ['sometimes', 'date_format:H:i'],
            'hora_fim' => ['sometimes', 'date_format:H:i'],
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

            $janela = $this->route('disponibilidade');
            $inicio = $this->input('hora_inicio', substr($janela->hora_inicio, 0, 5));
            $fim = $this->input('hora_fim', substr($janela->hora_fim, 0, 5));

            if ($fim <= $inicio) {
                $v->errors()->add('hora_fim', 'O fim deve ser depois do início.');
                return;
            }

            $sobrepoe = app(DisponibilidadeService::class)->existeSobreposicao(
                $janela->data->format('Y-m-d'), $inicio, $fim, $janela->id
            );

            if ($sobrepoe) {
                $v->errors()->add('hora_inicio', 'Já existe outra janela neste horário.');
            }
        });
    }
}
