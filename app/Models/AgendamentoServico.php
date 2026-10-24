<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tabela de ligação (chave composta, sem coluna id). */
class AgendamentoServico extends Model
{
    protected $table = 'agendamento_servico';
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;
    protected $guarded = [];

    protected $casts = [
        'quantidade' => 'integer',
        'preco_unitario' => 'decimal:2', // coluna criada pelo ajustes.sql
    ];

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class, 'agendamento_id');
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class, 'servico_id');
    }
}
