<?php

namespace App\Services\Agendamento;

use App\Contracts\AgendaExterna;
use Carbon\Carbon;

class GoogleCalendarAgenda implements AgendaExterna
{
    public function __construct(private GoogleCalendarService $calendar)
    {
    }

    public function ocupados(Carbon $inicio, Carbon $fim): array
    {
        return $this->calendar->ocupados($inicio, $fim);
    }
}
