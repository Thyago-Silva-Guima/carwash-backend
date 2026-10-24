<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Pertence ao Dev A. Versão mínima (STUB). */
class Servico extends Model
{
    protected $table = 'servico';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'preco_base' => 'decimal:2',
        'duracao_minutos' => 'integer', // coluna criada pelo ajustes.sql
        'ativo' => 'boolean',           // coluna criada pelo ajustes.sql
    ];

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
