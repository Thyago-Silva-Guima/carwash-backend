<?php

namespace App\Contracts;

use Carbon\Carbon;

interface AgendaExterna
{
    /** @return array<int, array{inicio: Carbon, fim: Carbon}> */
    public function ocupados(Carbon $inicio, Carbon $fim): array;
}
