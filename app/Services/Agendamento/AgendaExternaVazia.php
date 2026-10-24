<?php

namespace App\Services\Agendamento;

use App\Contracts\AgendaExterna;
use Carbon\Carbon;

/** STUB: descartar na integração (o Dev C registra a versão do Google). */
class AgendaExternaVazia implements AgendaExterna
{
    public function ocupados(Carbon $inicio, Carbon $fim): array
    {
        return [];
    }
}
