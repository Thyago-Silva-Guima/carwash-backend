<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class AgendamentoAnexo extends Model
{
    protected $table = 'anexo_agendamento';

    protected $fillable = ['agendamento_id', 'url', 'nome_original', 'mime_type', 'tamanho'];

    protected $hidden = ['url'];

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }

    public function urlTemporaria(int $minutos = 10): string
    {
        return URL::temporarySignedRoute(
            'anexos.show',
            now()->addMinutes($minutos),
            ['anexo' => $this->id]
        );
    }

    public function paraResposta(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome_original,
            'tamanho' => $this->tamanho,
            'url' => $this->urlTemporaria(),
        ];
    }
}
