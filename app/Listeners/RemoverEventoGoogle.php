<?php

namespace App\Listeners;

use App\Events\AgendamentoCancelado;
use App\Models\Agendamento;
use App\Services\Agendamento\GoogleCalendarService;
use Illuminate\Contracts\Queue\ShouldQueue;

class RemoverEventoGoogle implements ShouldQueue
{
    public int $tries = 5;

    public bool $afterCommit = true;

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function __construct(private readonly GoogleCalendarService $calendar)
    {
    }

    public function handle(AgendamentoCancelado $event): void
    {
        $calendar = $this->calendar;

        $agendamento = Agendamento::find($event->agendamento->id);

        if (! $agendamento || ! $agendamento->google_event_id) {
            return;
        }

        $eventId = $agendamento->google_event_id;
        $calendar->removerEvento($eventId);

        Agendamento::query()
            ->whereKey($agendamento->id)
            ->where('google_event_id', $eventId)
            ->update(['google_event_id' => null]);
    }
}
