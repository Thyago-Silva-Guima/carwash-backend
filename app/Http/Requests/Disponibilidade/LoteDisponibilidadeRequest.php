<?php

namespace App\Http\Requests\Disponibilidade;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LoteDisponibilidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data_inicio' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'data_fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:data_inicio'],
            'dias_semana' => ['required', 'array', 'min:1'],
            'dias_semana.*' => ['integer', 'between:0,6', 'distinct'],
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

            $dias = Carbon::parse($this->input('data_inicio'))->diffInDays(Carbon::parse($this->input('data_fim')));

            if ($dias > 366) {
                $v->errors()->add('data_fim', 'O período máximo é de 366 dias.');
            }
        });
    }
}
