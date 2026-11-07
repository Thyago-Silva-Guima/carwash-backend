<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Janela de atendimento cadastrada pelo dono (dono: Dev B). */
class Disponibilidade extends Model
{
    protected $table = 'disponibilidade';

    public $timestamps = false;

    protected $fillable = ['data', 'hora_inicio', 'hora_fim', 'capacidade', 'observacao', 'usuario_id'];

    protected function casts(): array
    {
        return [
            'data' => 'date:Y-m-d',
            'capacidade' => 'integer',
        ];
    }

    protected function horaInicio(): Attribute
    {
        return Attribute::make(set: fn ($valor) => $this->comSegundos($valor));
    }

    protected function horaFim(): Attribute
    {
        return Attribute::make(set: fn ($valor) => $this->comSegundos($valor));
    }

    private function comSegundos(string $valor): string
    {
        return strlen($valor) === 5 ? $valor . ':00' : $valor;
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function scopeDoDia(Builder $query, $data): Builder
    {
        return $query->whereDate('data', $data);
    }

    /** Início da janela como data e hora completas. */
    public function inicioEm(): Carbon
    {
        return Carbon::parse($this->data->format('Y-m-d') . ' ' . $this->hora_inicio);
    }

    /** Fim da janela como data e hora completas. */
    public function fimEm(): Carbon
    {
        return Carbon::parse($this->data->format('Y-m-d') . ' ' . $this->hora_fim);
    }
}