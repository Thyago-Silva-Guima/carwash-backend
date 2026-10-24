<?php

namespace App\Services\Agendamento;

use App\Events\AgendamentoCancelado;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Disponibilidade;
use App\Models\Servico;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgendamentoService
{
    public function __construct(private DisponibilidadeService $disponibilidade)
    {
    }

    /**
     * Cria um agendamento "pendente".
     *
     * @param array $dados já validados por StoreAgendamentoRequest:
     *   endereco_id, data_hora, servicos[{servico_id, quantidade}], observacoes
     */
    public function criar(Cliente $cliente, array $dados): Agendamento
    {
        $itens = collect($dados['servicos'])->map(fn ($i) => [
            'servico_id' => (int) $i['servico_id'],
            'quantidade' => (int) ($i['quantidade'] ?? 1),
        ]);

        // Preço e duração vêm SEMPRE do banco, nunca do que o front enviou.
        $servicos = Servico::whereIn('id', $itens->pluck('servico_id'))->get()->keyBy('id');

        $duracao = 0;
        $centavos = 0;

        foreach ($itens as $item) {
            $servico = $servicos[$item['servico_id']];
            $duracao += $servico->duracao_minutos * $item['quantidade'];
            $centavos += (int) round(((float) $servico->preco_base) * 100) * $item['quantidade'];
        }

        // O front manda com fuso (ex.: -03:00); no banco guardamos no fuso do app.
        $inicio = Carbon::parse($dados['data_hora'])
            ->setTimezone(config('app.timezone'))
            ->second(0);
        $fim = $inicio->copy()->addMinutes($duracao);

        $this->validarPrazos($inicio);

        // 1ª checagem, já com a agenda externa (Google), ANTES de travar o banco.
        $this->exigirHorarioLivre($inicio, $duracao, true);

        return DB::transaction(function () use ($cliente, $dados, $itens, $servicos, $inicio, $fim, $duracao, $centavos) {
            // Trava as janelas do dia: dois pedidos para o mesmo dia passam um de cada vez.
            Disponibilidade::doDia($inicio->toDateString())->lockForUpdate()->get();

            // 2ª checagem, agora protegida pelo lock (só banco, rápida).
            $this->exigirHorarioLivre($inicio, $duracao, false);

            $agendamento = Agendamento::create([
                'cliente_id' => $cliente->id,
                'endereco_id' => $dados['endereco_id'],
                'data_hora' => $inicio,
                'data_hora_fim' => $fim,
                'status' => Agendamento::STATUS_PENDENTE,
                'valor_total' => number_format($centavos / 100, 2, '.', ''),
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            foreach ($itens as $item) {
                $agendamento->servicos()->attach($item['servico_id'], [
                    'quantidade' => $item['quantidade'],
                    'preco_unitario' => $servicos[$item['servico_id']]->preco_base,
                ]);
            }

            return $agendamento->load('servicos');
        });
    }

    /** Cancelamento feito pelo cliente. */
    public function cancelar(Agendamento $agendamento): Agendamento
    {
        if (! in_array($agendamento->status, Agendamento::STATUS_QUE_BLOQUEIAM, true)) {
            abort(409, 'Este agendamento não pode mais ser cancelado.');
        }

        $horas = (int) config('agendamento.antecedencia_cancelar');

        if (now() > $agendamento->data_hora->copy()->subHours($horas)) {
            throw ValidationException::withMessages([
                'agendamento' => ["O cancelamento exige antecedência mínima de {$horas} hora(s)."],
            ]);
        }

        $agendamento->update([
            'status' => Agendamento::STATUS_CANCELADO,
            'motivo' => 'Cancelado pelo cliente',
        ]);

        // O Dev C escuta este evento para remover o evento do Google (se existir).
        AgendamentoCancelado::dispatch($agendamento);

        return $agendamento->fresh();
    }

    // ------------------------------------------------------------------

    private function validarPrazos(Carbon $inicio): void
    {
        $horas = (int) config('agendamento.antecedencia_agendar');
        $dias = (int) config('agendamento.limite_dias');

        if ($inicio < now()->addHours($horas)) {
            throw ValidationException::withMessages([
                'data_hora' => ["O agendamento exige antecedência mínima de {$horas} hora(s)."],
            ]);
        }

        if ($inicio > now()->addDays($dias)->endOfDay()) {
            throw ValidationException::withMessages([
                'data_hora' => ["Só é possível agendar com até {$dias} dias de antecedência."],
            ]);
        }
    }

    private function exigirHorarioLivre(Carbon $inicio, int $duracao, bool $consultarExterna): void
    {
        if (! $this->disponibilidade->horarioEstaLivre($inicio, $duracao, $consultarExterna)) {
            throw ValidationException::withMessages([
                'data_hora' => ['Este horário não está disponível.'],
            ]);
        }
    }
}
