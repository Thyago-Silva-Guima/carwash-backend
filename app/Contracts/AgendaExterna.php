<?php

namespace App\Contracts;

use Carbon\Carbon;

/**
 * CONTRATO fixo entre Dev B e Dev C. Não mudar nome nem assinatura.
 * Dev B usa; Dev C implementa com o Google Agenda.
 */
interface AgendaExterna
{
    /** @return array<int, array{inicio: Carbon, fim: Carbon}> */
    public function ocupados(Carbon $inicio, Carbon $fim): array;
}
