<?php

namespace App\Services\Agendamento;

use App\Contracts\AgendaExterna;
use App\Models\Agendamento;
use App\Models\Disponibilidade;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DisponibilidadeService
{
    public function __construct(private AgendaExterna $agenda)
    {
    }

    // ------------------------------------------------------------------
    // Janelas do dono
    // ------------------------------------------------------------------

    /** Existe outra janela no mesmo dia que se sobreponha a [inicio, fim)? */
    public function existeSobreposicao(string $data, string $inicio, string $fim, ?int $ignorarId = null): bool
    {
        return Disponibilidade::query()
            ->whereDate('data', $data)
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->where('hora_inicio', '<', $this->hms($fim))
            ->where('hora_fim', '>', $this->hms($inicio))
            ->exists();
    }

    /**
     * Agendamentos (pendente/confirmado) que ficariam fora da janela.
     * Sem novos horários = a janela será apagada: todos os que estão nela.
     */
    public function agendamentosAfetados(Disponibilidade $janela, ?string $novoInicio = null, ?string $novoFim = null): Collection
    {
        $dia = $janela->data->format('Y-m-d');
        $inicioAtual = Carbon::parse($dia . ' ' . $janela->hora_inicio);
        $fimAtual = Carbon::parse($dia . ' ' . $janela->hora_fim);

        $naJanela = Agendamento::queBloqueiam()
            ->where('data_hora', '>=', $inicioAtual)
            ->where('data_hora_fim', '<=', $fimAtual)
            ->get();

        if ($novoInicio === null || $novoFim === null) {
            return $naJanela;
        }

        $novoInicioEm = Carbon::parse($dia . ' ' . $this->hms($novoInicio));
        $novoFimEm = Carbon::parse($dia . ' ' . $this->hms($novoFim));

        return $naJanela
            ->filter(fn ($a) => $a->data_hora < $novoInicioEm || $a->data_hora_fim > $novoFimEm)
            ->values();
    }

    /**
     * Cria uma janela por dia dentro do período, só nos dias da semana pedidos.
     * Dias que já têm janela sobreposta são ignorados (e listados no retorno).
     *
     * @return array{criadas: int, ignoradas: array<int, array{data: string, motivo: string}>}
     */
    public function criarEmLote(array $dados, int $usuarioId): array
    {
        $criadas = 0;
        $ignoradas = [];
        $dia = Carbon::parse($dados['data_inicio'])->startOfDay();
        $ultimo = Carbon::parse($dados['data_fim'])->startOfDay();
        $diasSemana = array_map('intval', $dados['dias_semana']);

        DB::transaction(function () use (&$criadas, &$ignoradas, $dia, $ultimo, $diasSemana, $dados, $usuarioId) {
            while ($dia <= $ultimo) {
                if (in_array($dia->dayOfWeek, $diasSemana, true)) {
                    $data = $dia->toDateString();

                    if ($this->existeSobreposicao($data, $dados['hora_inicio'], $dados['hora_fim'])) {
                        $ignoradas[] = ['data' => $data, 'motivo' => 'Já existe uma janela neste horário.'];
                    } else {
                        Disponibilidade::create([
                            'data' => $data,
                            'hora_inicio' => $dados['hora_inicio'],
                            'hora_fim' => $dados['hora_fim'],
                            'capacidade' => $dados['capacidade'] ?? 1,
                            'observacao' => $dados['observacao'] ?? null,
                            'usuario_id' => $usuarioId,
                        ]);
                        $criadas++;
                    }
                }

                $dia->addDay();
            }
        });

        return ['criadas' => $criadas, 'ignoradas' => $ignoradas];
    }

    // ------------------------------------------------------------------
    // Consulta do cliente
    // ------------------------------------------------------------------

    /**
     * Horários livres de um dia, no formato [{inicio, fim}] (ISO 8601).
     *
     * @return array<int, array{inicio: string, fim: string}>
     */
    public function horariosLivres(Carbon $data, int $duracao, bool $consultarExterna = true): array
    {
        return collect($this->slotsDoDia($data, $duracao, $consultarExterna))
            ->map(fn ($h) => [
                'inicio' => $h['inicio']->format(DATE_ATOM),
                'fim' => $h['fim']->format(DATE_ATOM),
            ])
            ->all();
    }

    /** O horário que começa em $inicio está livre para um serviço dessa duração? */
    public function horarioEstaLivre(Carbon $inicio, int $duracao, bool $consultarExterna = true): bool
    {
        foreach ($this->slotsDoDia($inicio, $duracao, $consultarExterna) as $h) {
            if ($h['inicio']->getTimestamp() === $inicio->getTimestamp()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dias do mês que têm pelo menos um horário livre.
     * Faz UMA consulta de janelas, UMA de agendamentos e UMA à agenda externa para o mês todo.
     *
     * @return array<int, array{data: string, livres: int}>
     */
    public function diasComVaga(string $mes, int $duracao): array
    {
        $inicioMes = Carbon::parse($mes . '-01')->startOfDay();
        $fimMes = $inicioMes->copy()->endOfMonth();

        $janelas = Disponibilidade::query()
            ->whereBetween('data', [$inicioMes->toDateString(), $fimMes->toDateString()])
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->get()
            ->groupBy(fn ($j) => $j->data->format('Y-m-d'));

        if ($janelas->isEmpty()) {
            return [];
        }

        $agendamentos = $this->agendamentosEntre($inicioMes, $fimMes);
        $externos = $this->ocupadosExternos($inicioMes, $fimMes);

        $dias = [];

        foreach ($janelas as $dia => $janelasDoDia) {
            $livres = count($this->calcularDia($janelasDoDia, $duracao, $agendamentos, $externos));

            if ($livres > 0) {
                $dias[] = ['data' => $dia, 'livres' => $livres];
            }
        }

        return $dias;
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    /** @return array<int, array{inicio: \DateTimeImmutable, fim: \DateTimeImmutable}> */
    private function slotsDoDia(Carbon $data, int $duracao, bool $consultarExterna): array
    {
        $inicioDia = $data->copy()->startOfDay();
        $fimDia = $data->copy()->endOfDay();

        $janelas = Disponibilidade::query()
            ->whereDate('data', $inicioDia->toDateString())
            ->orderBy('hora_inicio')
            ->get();

        if ($janelas->isEmpty()) {
            return [];
        }

        $agendamentos = $this->agendamentosEntre($inicioDia, $fimDia);
        $externos = $consultarExterna ? $this->ocupadosExternos($inicioDia, $fimDia) : [];

        return $this->calcularDia($janelas, $duracao, $agendamentos, $externos);
    }

    /** @return array<int, array{inicio: \DateTimeImmutable, fim: \DateTimeImmutable}> */
    private function calcularDia(Collection $janelas, int $duracao, array $agendamentos, array $externos): array
    {
        $minimo = now()->addHours((int) config('agendamento.antecedencia_agendar'));
        $limite = now()->addDays((int) config('agendamento.limite_dias'))->endOfDay();
        $passo = (int) config('agendamento.passo_minutos');

        $todos = [];

        foreach ($janelas as $janela) {
            $todos = array_merge($todos, CalculadoraHorarios::horarios(
                $janela->inicioEm(),
                $janela->fimEm(),
                $duracao,
                $passo,
                (int) $janela->capacidade,
                $agendamentos,
                $externos,
                $minimo,
                $limite
            ));
        }

        return $todos;
    }

    /** Intervalos já reservados (pendente/confirmado) que tocam o período. */
    private function agendamentosEntre(Carbon $inicio, Carbon $fim): array
    {
        return Agendamento::queBloqueiam()
            ->sobrepoe($inicio, $fim)
            ->get(['data_hora', 'data_hora_fim'])
            ->map(fn ($a) => [$a->data_hora, $a->data_hora_fim])
            ->all();
    }

    /**
     * Intervalos ocupados na agenda externa (Google).
     * Se ela falhar, usamos só o banco e registramos um aviso.
     */
    private function ocupadosExternos(Carbon $inicio, Carbon $fim): array
    {
        try {
            return collect($this->agenda->ocupados($inicio, $fim))
                ->map(fn ($o) => [$o['inicio'], $o['fim']])
                ->all();
        } catch (Throwable $e) {
            Log::warning('Agenda externa indisponível; usando só o banco.', ['erro' => $e->getMessage()]);

            return [];
        }
    }

    /** Aceita "08:00" ou "08:00:00" e devolve sempre "08:00:00". */
    private function hms(string $hora): string
    {
        return strlen($hora) === 5 ? $hora . ':00' : $hora;
    }
}
