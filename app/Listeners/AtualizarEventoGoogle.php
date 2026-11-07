<?php

namespace App\Listeners;

use App\Events\AgendamentoAtualizado;
use App\Models\Agendamento;
use App\Services\Agendamento\GoogleCalendarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class AtualizarEventoGoogle implements ShouldQueue
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

    public function handle(AgendamentoAtualizado $event): void
    {
        $calendar = $this->calendar;

        $agendamento = Agendamento::find($event->agendamento->id);

        if (! $agendamento || $agendamento->status !== Agendamento::STATUS_CONFIRMADO) {
            return;
        }

        $eventId = $agendamento->google_event_id
            ? $calendar->atualizarEvento($agendamento->google_event_id, $agendamento)
            : $calendar->criarEvento($agendamento);

        DB::transaction(function () use ($agendamento, $eventId, $calendar) {
            $locked = Agendamento::query()->lockForUpdate()->find($agendamento->id);

            if (! $locked || $locked->status !== Agendamento::STATUS_CONFIRMADO) {
                return;
            }

            $locked->update(['google_event_id' => $eventId]);
        });

        $fresh = $agendamento->fresh();

        if (! $fresh || $fresh->status !== Agendamento::STATUS_CONFIRMADO) {
            $calendar->removerEvento($eventId);
        }
    }
}
