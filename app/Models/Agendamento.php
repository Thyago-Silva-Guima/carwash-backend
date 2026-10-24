<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agendamento extends Model
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_CONFIRMADO = 'confirmado';
    public const STATUS_CONCLUIDO = 'concluido';
    public const STATUS_CANCELADO = 'cancelado';

    /** Status que ocupam o horário. */
    public const STATUS_QUE_BLOQUEIAM = [self::STATUS_PENDENTE, self::STATUS_CONFIRMADO];

    protected $table = 'agendamento';
    public $timestamps = true; // created_at/updated_at vêm do ajustes.sql
    protected $guarded = [];

    protected $casts = [
        'data_hora' => 'datetime',
        'data_hora_fim' => 'datetime', // coluna criada pelo ajustes.sql
        'valor_total' => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function endereco(): BelongsTo
    {
        return $this->belongsTo(Endereco::class, 'endereco_id');
    }

    public function atendente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(AgendamentoServico::class, 'agendamento_id');
    }

    public function servicos(): BelongsToMany
    {
        return $this->belongsToMany(Servico::class, 'agendamento_servico', 'agendamento_id', 'servico_id')
            ->withPivot('quantidade', 'preco_unitario'); // preco_unitario vem do ajustes.sql
    }

    /** Só os agendamentos que ocupam horário (pendente e confirmado). */
    public function scopeQueBloqueiam(Builder $query): Builder
    {
        return $query->whereIn('status', self::STATUS_QUE_BLOQUEIAM);
    }

    /** Agendamentos que se sobrepõem ao intervalo [inicio, fim). */
    public function scopeSobrepoe(Builder $query, Carbon $inicio, Carbon $fim): Builder
    {
        return $query->where('data_hora', '<', $fim)
                     ->where('data_hora_fim', '>', $inicio);
    }
}
