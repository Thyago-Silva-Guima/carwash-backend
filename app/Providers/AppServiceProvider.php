<?php

namespace App\Providers;

use App\Contracts\AgendaExterna;
use App\Events\AgendamentoAtualizado;
use App\Events\AgendamentoCancelado;
use App\Events\AgendamentoConfirmado;
use App\Listeners\AtualizarEventoGoogle;
use App\Listeners\CriarEventoGoogle;
use App\Listeners\RemoverEventoGoogle;
use App\Services\Agendamento\AgendaExternaVazia;
use App\Services\Agendamento\GoogleCalendarAgenda;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AgendaExterna::class, function ($app) {
            // Sem Google configurado, em ambiente local ou de testes, usa a agenda vazia.
            // Em produção, a falta de configuração continua sendo erro (503).
            if ($this->semGoogleEmAmbienteDeDesenvolvimento()) {
                return new AgendaExternaVazia();
            }

            return $app->make(GoogleCalendarAgenda::class);
        });
    }

    public function boot(): void
    {
        // Dev C: os eventos do Google só são ligados quando há Google configurado
        // (ou em produção). Em local/testes sem credenciais, confirmar um pedido não chama o Google.
        if (! $this->semGoogleEmAmbienteDeDesenvolvimento()) {
            Event::listen(AgendamentoConfirmado::class, CriarEventoGoogle::class);
            Event::listen(AgendamentoAtualizado::class, AtualizarEventoGoogle::class);
            Event::listen(AgendamentoCancelado::class, RemoverEventoGoogle::class);
        }

        // Dev A: limites de tentativas.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('minuto:'.$request->ip()),
            Limit::perHour(30)->by('hora:'.$request->ip()),
        ]);

        RateLimiter::for('registro', fn (Request $request) => [
            Limit::perHour(10)->by('registro:'.$request->ip()),
        ]);
    }

    private function semGoogleEmAmbienteDeDesenvolvimento(): bool
    {
        return blank(config('google_calendar.calendar_id'))
            && $this->app->environment(['local', 'testing']);
    }
}
