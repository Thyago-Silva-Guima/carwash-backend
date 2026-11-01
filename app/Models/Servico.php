<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Servico extends Model
{
    protected $table = 'servico';

    public $timestamps = false;

    protected $fillable = [
        'nome', 'descricao', 'categoria', 'duracao_minutos', 'preco_base', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'duracao_minutos' => 'integer',
            'preco_base' => 'decimal:2',
        ];
    }

    public function scopeAtivos(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('ativo', true);
    }

    public function agendamentos(): BelongsToMany
    {
        return $this->belongsToMany(Agendamento::class, 'agendamento_servico')
            ->withPivot('quantidade', 'preco_unitario');
    }
}
