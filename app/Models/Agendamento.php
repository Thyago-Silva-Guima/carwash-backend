<?php

namespace App\Models;

use App\Events\AgendamentoAtualizado;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Model unificado (dono: Dev B). O status é TEXTO, com constantes: é assim que os
 * módulos de disponibilidade/agendamento (B) e do painel/Google (C) comparam.
 */
class Agendamento extends Model
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_CONFIRMADO = 'confirmado';
    public const STATUS_CONCLUIDO = 'concluido';
    public const STATUS_CANCELADO = 'cancelado';

    /** Status que ocupam o horário. */
    public const STATUS_QUE_BLOQUEIAM = [self::STATUS_PENDENTE, self::STATUS_CONFIRMADO];

    protected $table = 'agendamento';

    protected $fillable = [
        'cliente_id', 'endereco_id', 'usuario_id',
        'data_hora', 'data_hora_fim',
        'status', 'valor_total', 'observacoes', 'motivo', 'google_event_id',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDENTE,
    ];

    protected function casts(): array
    {
        return [
            'data_hora' => 'datetime',
            'data_hora_fim' => 'datetime',
            'valor_total' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function endereco(): BelongsTo
    {
        return $this->belongsTo(Endereco::class);
    }

    /** Quem da equipe confirmou o pedido. */
    public function atendente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(AgendamentoServico::class, 'agendamento_id');
    }

    public function servicos(): BelongsToMany
    {
        return $this->belongsToMany(Servico::class, 'agendamento_servico', 'agendamento_id', 'servico_id')
            ->withPivot('quantidade', 'preco_unitario');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(AgendamentoAnexo::class);
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDENTE);
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

    protected static function booted(): void
    {
        // Dev C: pedido já confirmado que muda de horário/observação atualiza o evento do Google.
        static::updated(function (Agendamento $agendamento) {
            if ($agendamento->status === self::STATUS_CONFIRMADO
                && $agendamento->wasChanged(['data_hora', 'data_hora_fim', 'observacoes'])
            ) {
                AgendamentoAtualizado::dispatch($agendamento);
            }
        });

        // Dev A: apaga os arquivos dos anexos junto com o agendamento.
        static::deleting(function (Agendamento $agendamento) {
            $agendamento->anexos->each(
                fn ($anexo) => Storage::disk('local')->delete($anexo->url)
            );
        });
    }
}
