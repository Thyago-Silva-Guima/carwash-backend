<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendamentoAnexo extends Model
{
    protected $table = 'anexo_agendamento';

    public const UPDATED_AT = null;

    protected $fillable = ['agendamento_id', 'url', 'nome_original', 'mime_type', 'tamanho'];

    protected $hidden = ['url'];

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }

    public function paraResposta(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome_original,
            'mime_type' => $this->mime_type,
            'tamanho' => $this->tamanho,
        ];
    }
}