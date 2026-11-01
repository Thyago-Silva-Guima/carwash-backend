<?php

namespace App\Listeners;

use App\Events\AgendamentoConfirmado;
use App\Models\Agendamento;
use App\Services\Agendamento\GoogleCalendarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class CriarEventoGoogle implements ShouldQueue
{
    public int $tries = 5;

    public bool $afterCommit = true;

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(AgendamentoConfirmado $event, GoogleCalendarService $calendar): void
    {
        $agendamento = Agendamento::find($event->agendamento->id);

        if (! $agendamento || $agendamento->status !== Agendamento::STATUS_CONFIRMADO) {
            return;
        }

        if ($agendamento->google_event_id) {
            $eventId = $calendar->atualizarEvento($agendamento->google_event_id, $agendamento);
            DB::transaction(function () use ($agendamento, $eventId) {
                $locked = Agendamento::query()->lockForUpdate()->find($agendamento->id);

                if ($locked && $locked->status === Agendamento::STATUS_CONFIRMADO) {
                    $locked->update(['google_event_id' => $eventId]);
                }
            });

            $fresh = $agendamento->fresh();

            if (! $fresh || $fresh->status !== Agendamento::STATUS_CONFIRMADO) {
                $calendar->removerEvento($eventId);
            }

            return;
        }

        $googleEventId = $calendar->criarEvento($agendamento);

        DB::transaction(function () use ($agendamento, $googleEventId, $calendar) {
            $locked = Agendamento::query()->lockForUpdate()->find($agendamento->id);

            if (! $locked || $locked->status !== Agendamento::STATUS_CONFIRMADO) {
                return;
            }

            $locked->update(['google_event_id' => $googleEventId]);
        });

        $fresh = $agendamento->fresh();

        if (! $fresh || $fresh->status !== Agendamento::STATUS_CONFIRMADO) {
            $calendar->removerEvento($googleEventId);
        }
    }
}
