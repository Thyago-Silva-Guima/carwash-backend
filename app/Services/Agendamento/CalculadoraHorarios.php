<?php

namespace App\Services\Agendamento;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Cálculo PURO dos horários livres de UMA janela.
 *
 * Não usa banco de dados nem Laravel: recebe tudo pronto e devolve a lista.
 * Por isso é a peça mais fácil de testar (ver tests/Unit/CalculoHorariosTest.php).
 *
 * Usa intervalos semiabertos [início, fim): um serviço que termina às 10:00
 * não colide com outro que começa às 10:00.
 */
final class CalculadoraHorarios
{
    /**
     * @param DateTimeInterface $janelaInicio       início da janela do dono
     * @param DateTimeInterface $janelaFim          fim da janela do dono
     * @param int               $duracaoMinutos     duração total do serviço
     * @param int               $passoMinutos       de quanto em quanto tempo começa um horário
     * @param int               $capacidade         atendimentos simultâneos permitidos
     * @param array<int, array{0: DateTimeInterface, 1: DateTimeInterface}> $agendamentos intervalos já reservados (pendente/confirmado)
     * @param array<int, array{0: DateTimeInterface, 1: DateTimeInterface}> $externos     intervalos ocupados na agenda externa (Google)
     * @param DateTimeInterface $antecedenciaMinima nenhum horário começa antes disso
     * @param DateTimeInterface $limite             nenhum horário começa depois disso
     *
     * @return array<int, array{inicio: DateTimeImmutable, fim: DateTimeImmutable}>
     */
    public static function horarios(
        DateTimeInterface $janelaInicio,
        DateTimeInterface $janelaFim,
        int $duracaoMinutos,
        int $passoMinutos,
        int $capacidade,
        array $agendamentos,
        array $externos,
        DateTimeInterface $antecedenciaMinima,
        DateTimeInterface $limite
    ): array {
        if ($duracaoMinutos <= 0 || $passoMinutos <= 0) {
            throw new InvalidArgumentException('Duração e passo precisam ser maiores que zero.');
        }

        $inicio = DateTimeImmutable::createFromInterface($janelaInicio);
        $fimJanela = DateTimeImmutable::createFromInterface($janelaFim);
        $minimo = DateTimeImmutable::createFromInterface($antecedenciaMinima);
        $maximo = DateTimeImmutable::createFromInterface($limite);

        $resultado = [];

        while (true) {
            $fim = $inicio->modify("+{$duracaoMinutos} minutes");

            // O serviço precisa caber INTEIRO dentro da janela.
            if ($fim > $fimJanela) {
                break;
            }

            if ($inicio >= $minimo
                && $inicio <= $maximo
                && self::contarSobrepostos($agendamentos, $inicio, $fim) < $capacidade
                && self::contarSobrepostos($externos, $inicio, $fim) === 0
            ) {
                $resultado[] = ['inicio' => $inicio, 'fim' => $fim];
            }

            $inicio = $inicio->modify("+{$passoMinutos} minutes");
        }

        return $resultado;
    }

    /**
     * Quantos intervalos se sobrepõem a [inicio, fim).
     *
     * @param array<int, array{0: DateTimeInterface, 1: DateTimeInterface}> $intervalos
     */
    private static function contarSobrepostos(array $intervalos, DateTimeImmutable $inicio, DateTimeImmutable $fim): int
    {
        $total = 0;

        foreach ($intervalos as [$inicioOcupado, $fimOcupado]) {
            if ($inicioOcupado < $fim && $fimOcupado > $inicio) {
                $total++;
            }
        }

        return $total;
    }
}
