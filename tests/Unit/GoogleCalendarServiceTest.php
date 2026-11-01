<?php

namespace Tests\Unit;

use App\Models\Agendamento;
use App\Services\Agendamento\GoogleCalendarException;
use App\Services\Agendamento\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarServiceTest extends TestCase
{
    private string $credentialsFile;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('google_calendar_access_token');

        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        if ($key === false || ! openssl_pkey_export($key, $privateKey)) {
            $this->fail('Não foi possível gerar uma chave temporária para o teste.');
        }

        $this->credentialsFile = tempnam(sys_get_temp_dir(), 'calendar-test-');
        file_put_contents($this->credentialsFile, json_encode([
            'client_email' => 'calendar-test@example.iam.gserviceaccount.com',
            'private_key' => $privateKey,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ], JSON_THROW_ON_ERROR));

        config([
            'google_calendar.calendar_id' => 'calendar.with.dots@example.com',
            'google_calendar.credentials_file' => $this->credentialsFile,
            'google_calendar.api_base_uri' => 'https://www.googleapis.com/calendar/v3',
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->credentialsFile) && is_file($this->credentialsFile)) {
            unlink($this->credentialsFile);
        }

        Cache::forget('google_calendar_access_token');
        parent::tearDown();
    }

    public function test_it_returns_busy_periods_from_freebusy(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token'], 200),
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => [
                    'calendar.with.dots@example.com' => [
                        'busy' => [[
                            'start' => '2026-11-14T09:00:00-03:00',
                            'end' => '2026-11-14T10:00:00-03:00',
                        ]],
                    ],
                ],
            ], 200),
        ]);

        $periods = app(GoogleCalendarService::class)->ocupados(
            Carbon::parse('2026-11-14T08:00:00-03:00'),
            Carbon::parse('2026-11-14T12:00:00-03:00')
        );

        $this->assertCount(1, $periods);
        $this->assertSame('2026-11-14 09:00:00', $periods[0]['inicio']->toDateTimeString());
        $this->assertSame('2026-11-14 10:00:00', $periods[0]['fim']->toDateTimeString());
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization'));
    }

    public function test_it_fails_when_google_reports_calendar_permission_errors(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token'], 200),
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => [
                    'calendar.with.dots@example.com' => [
                        'errors' => [['reason' => 'forbidden']],
                        'busy' => [],
                    ],
                ],
            ], 200),
        ]);

        $this->expectException(GoogleCalendarException::class);

        app(GoogleCalendarService::class)->ocupados(
            Carbon::parse('2026-11-14T08:00:00-03:00'),
            Carbon::parse('2026-11-14T12:00:00-03:00')
        );
    }

    public function test_it_reuses_a_deterministic_event_id_after_a_duplicate_insert(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token'], 200),
            'https://www.googleapis.com/calendar/v3/*' => function (Request $request) {
                if ($request->method() === 'POST') {
                    return Http::response([], 409);
                }

                return Http::response(['id' => hash('sha256', 'carwash-agendamento-42')], 200);
            },
        ]);

        $agendamento = new Agendamento;
        $agendamento->setRawAttributes([
            'id' => 42,
            'status' => Agendamento::STATUS_CONFIRMADO,
            'data_hora' => '2026-11-14T09:00:00-03:00',
            'data_hora_fim' => '2026-11-14T10:00:00-03:00',
        ]);

        $eventId = app(GoogleCalendarService::class)->criarEvento($agendamento);

        $this->assertSame(hash('sha256', 'carwash-agendamento-42'), $eventId);
        Http::assertSentCount(3);
    }
}
