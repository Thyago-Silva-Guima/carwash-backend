<?php

namespace Tests\Unit;

use App\Services\Agendamento\CalculadoraHorarios;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Testa SÓ a conta dos horários livres. Não usa banco nem Laravel.
 * Rodar: php artisan test --filter=CalculoHorariosTest
 */
class CalculoHorariosTest extends TestCase
{
    private function dt(int $h, int $m = 0): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf('2026-11-14 %02d:%02d:00', $h, $m));
    }

    /** Devolve só as horas de início, ex.: ['08:00', '08:30']. */
    private function calcular(
        int $duracao,
        int $passo = 30,
        int $capacidade = 1,
        array $agendamentos = [],
        array $externos = [],
        ?DateTimeImmutable $minimo = null,
        ?DateTimeImmutable $limite = null,
        int $janelaInicio = 8,
        int $janelaFim = 12
    ): array {
        $slots = CalculadoraHorarios::horarios(
            $this->dt($janelaInicio),
            $this->dt($janelaFim),
            $duracao,
            $passo,
            $capacidade,
            $agendamentos,
            $externos,
            $minimo ?? new DateTimeImmutable('2026-01-01 00:00:00'),
            $limite ?? new DateTimeImmutable('2027-12-31 00:00:00')
        );

        return array_map(fn ($s) => $s['inicio']->format('H:i'), $slots);
    }

    public function test_janela_de_8_as_12_com_servico_de_60_min_gera_7_horarios(): void
    {
        $this->assertSame(
            ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00'],
            $this->calcular(60)
        );
    }

    public function test_servico_de_90_min_tem_ultimo_inicio_as_10_30(): void
    {
        $this->assertSame(
            ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30'],
            $this->calcular(90)
        );
    }

    public function test_servico_maior_que_a_janela_nao_gera_horarios(): void
    {
        $this->assertSame([], $this->calcular(300));
    }

    public function test_agendamento_existente_bloqueia_so_o_que_colide(): void
    {
        $ocupado = [[$this->dt(9), $this->dt(10)]];

        $this->assertSame(['08:00', '10:00', '11:00'], $this->calcular(60, 60, 1, $ocupado));
    }

    public function test_agendamento_que_termina_quando_outro_comeca_nao_colide(): void
    {
        $ocupado = [[$this->dt(8), $this->dt(9)]];

        // 08:00 colide; 09:00 não (intervalos semiabertos).
        $this->assertSame(['09:00', '10:00', '11:00'], $this->calcular(60, 60, 1, $ocupado));
    }

    public function test_capacidade_2_aceita_o_segundo_e_recusa_o_terceiro(): void
    {
        $um = [[$this->dt(9), $this->dt(10)]];
        $dois = [[$this->dt(9), $this->dt(10)], [$this->dt(9), $this->dt(10)]];

        $this->assertContains('09:00', $this->calcular(60, 60, 2, $um));
        $this->assertNotContains('09:00', $this->calcular(60, 60, 2, $dois));
    }

    public function test_evento_externo_bloqueia_o_horario(): void
    {
        $google = [[$this->dt(10), $this->dt(11)]];

        $this->assertSame(['08:00', '09:00', '11:00'], $this->calcular(60, 60, 1, [], $google));
    }

    public function test_antecedencia_minima_remove_horarios_anteriores(): void
    {
        $this->assertSame(['10:00', '11:00'], $this->calcular(60, 60, 1, [], [], $this->dt(10)));
    }

    public function test_limite_remove_horarios_posteriores(): void
    {
        $this->assertSame(['08:00', '09:00'], $this->calcular(60, 60, 1, [], [], null, $this->dt(9)));
    }

    public function test_duracao_zero_lanca_excecao(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calcular(0);
    }
}
