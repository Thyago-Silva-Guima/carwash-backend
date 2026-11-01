<?php

namespace App\Services\Agendamento;

use App\Models\Agendamento;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleCalendarService
{
    private const SCOPE = 'https://www.googleapis.com/auth/calendar';

    /**
     * @return array<int, array{inicio: Carbon, fim: Carbon}>
     */
    public function ocupados(Carbon $inicio, Carbon $fim): array
    {
        $calendarId = $this->calendarId();
        $response = $this->request('post', '/freeBusy', [
            'timeMin' => $inicio->toIso8601String(),
            'timeMax' => $fim->toIso8601String(),
            'items' => [['id' => $calendarId]],
        ]);

        $calendars = $response->json('calendars');
        $calendar = is_array($calendars) ? ($calendars[$calendarId] ?? null) : null;

        if (! is_array($calendar) || ! empty($calendar['errors'])) {
            throw new GoogleCalendarException('O Google Calendar não permitiu consultar a agenda.');
        }

        $periodos = $calendar['busy'] ?? null;

        if (! is_array($periodos)) {
            throw new GoogleCalendarException('Resposta inválida do Google Calendar.');
        }

        return array_map(fn (array $periodo) => [
            'inicio' => Carbon::parse($periodo['start']),
            'fim' => Carbon::parse($periodo['end']),
        ], $periodos);
    }

    public function criarEvento(Agendamento $agendamento): string
    {
        $eventId = hash('sha256', 'carwash-agendamento-' . $agendamento->id);
        $path = '/calendars/' . rawurlencode($this->calendarId()) . '/events';
        $response = $this->request('post', $path, $this->eventPayload($agendamento) + ['id' => $eventId], [409]);

        if ($response->status() === 409) {
            $this->atualizarEvento($eventId, $agendamento);

            return $eventId;
        }

        $returnedId = $response->json('id');

        if (! is_string($returnedId) || $returnedId === '') {
            throw new GoogleCalendarException('O Google Calendar não retornou o identificador do evento.');
        }

        return $returnedId;
    }

    public function atualizarEvento(string $eventId, Agendamento $agendamento): string
    {
        $path = '/calendars/' . rawurlencode($this->calendarId()) . '/events/' . rawurlencode($eventId);
        $response = $this->request('put', $path, $this->eventPayload($agendamento), [404]);

        if ($response->status() === 404) {
            return $this->criarEvento($agendamento);
        }

        $returnedId = $response->json('id');

        return is_string($returnedId) && $returnedId !== '' ? $returnedId : $eventId;
    }

    public function removerEvento(string $eventId): void
    {
        $path = '/calendars/' . rawurlencode($this->calendarId()) . '/events/' . rawurlencode($eventId);
        $this->request('delete', $path, null, [404]);
    }

    private function eventPayload(Agendamento $agendamento): array
    {
        return [
            'summary' => 'CarWash - Agendamento #' . $agendamento->id,
            'description' => 'Status: ' . $agendamento->status,
            'start' => [
                'dateTime' => $agendamento->data_hora->toIso8601String(),
                'timeZone' => config('app.timezone'),
            ],
            'end' => [
                'dateTime' => $agendamento->data_hora_fim->toIso8601String(),
                'timeZone' => config('app.timezone'),
            ],
        ];
    }

    private function request(string $method, string $path, ?array $payload = null, array $acceptedStatuses = []): Response
    {
        $uri = rtrim((string) config('google_calendar.api_base_uri'), '/') . $path;
        $request = Http::timeout(15)->withToken($this->accessToken())->acceptJson();

        $response = match ($method) {
            'post' => $request->post($uri, $payload ?? []),
            'put' => $request->put($uri, $payload ?? []),
            'delete' => $request->delete($uri),
            default => throw new RuntimeException('Método HTTP não suportado.'),
        };

        if (! $response->successful() && ! in_array($response->status(), $acceptedStatuses, true)) {
            throw new GoogleCalendarException(
                'Falha ao comunicar com o Google Calendar (HTTP ' . $response->status() . ').',
                $response->status()
            );
        }

        return $response;
    }

    private function accessToken(): string
    {
        return Cache::remember('google_calendar_access_token', now()->addMinutes(55), function () {
            $credentialsFile = config('google_calendar.credentials_file');

            if (! is_string($credentialsFile) || $credentialsFile === '' || ! is_readable($credentialsFile)) {
                throw new GoogleCalendarException('Credenciais do Google Calendar não configuradas.');
            }

            $credentials = json_decode(file_get_contents($credentialsFile), true);

            if (! is_array($credentials)
                || empty($credentials['client_email'])
                || empty($credentials['private_key'])
                || empty($credentials['token_uri'])
            ) {
                throw new GoogleCalendarException('Arquivo de credenciais do Google inválido.');
            }

            $issuedAt = time();
            $assertion = $this->signedAssertion([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $credentials['token_uri'],
                'iat' => $issuedAt,
                'exp' => $issuedAt + 3600,
            ], $credentials['private_key']);

            $response = Http::asForm()->timeout(15)->post($credentials['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw new GoogleCalendarException(
                    'Não foi possível autenticar no Google Calendar (HTTP ' . $response->status() . ').',
                    $response->status()
                );
            }

            return $response->json('access_token');
        });
    }

    private function signedAssertion(array $claims, string $privateKey): string
    {
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64Url(json_encode($claims, JSON_THROW_ON_ERROR));
        $unsigned = $header . '.' . $payload;
        $key = openssl_pkey_get_private($privateKey);

        if ($key === false || ! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new GoogleCalendarException('Não foi possível assinar a autenticação do Google Calendar.');
        }

        return $unsigned . '.' . $this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function calendarId(): string
    {
        $calendarId = config('google_calendar.calendar_id');

        if (! is_string($calendarId) || $calendarId === '') {
            throw new GoogleCalendarException('ID da agenda do Google Calendar não configurado.');
        }

        return $calendarId;
    }
}
