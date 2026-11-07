<?php

namespace App\Services\Agendamento;

use App\Events\AgendamentoCancelado;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Disponibilidade;
use App\Models\Servico;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AgendamentoService
{
    public function __construct(private DisponibilidadeService $disponibilidade)
    {
    }

    /**
     * Cria um agendamento "pendente".
     *
     * @param array $dados já validados por StoreAgendamentoRequest:
     *   endereco_id, data_hora, servicos[{servico_id, quantidade}], observacoes, anexos[UploadedFile] (opcional)
     */
    public function criar(Cliente $cliente, array $dados): Agendamento
    {
        $anexos = $dados['anexos'] ?? [];

        $itens = collect($dados['servicos'])->map(fn ($i) => [
            'servico_id' => (int) $i['servico_id'],
            'quantidade' => (int) ($i['quantidade'] ?? 1),
        ]);

        $servicos = Servico::whereIn('id', $itens->pluck('servico_id'))->get()->keyBy('id');

        $duracao = 0;
        $centavos = 0;

        foreach ($itens as $item) {
            $servico = $servicos[$item['servico_id']];
            $duracao += $servico->duracao_minutos * $item['quantidade'];
            $centavos += (int) round(((float) $servico->preco_base) * 100) * $item['quantidade'];
        }

        $inicio = Carbon::parse($dados['data_hora'])
            ->setTimezone(config('app.timezone'))
            ->second(0);
        $fim = $inicio->copy()->addMinutes($duracao);

        $this->validarPrazos($inicio);

        $this->exigirHorarioLivre($inicio, $duracao, true);

        $arquivosSalvos = [];

        try {
            return DB::transaction(function () use ($cliente, $dados, $itens, $servicos, $inicio, $fim, $duracao, $centavos, $anexos, &$arquivosSalvos) {
                Disponibilidade::doDia($inicio->toDateString())->lockForUpdate()->get();

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

                foreach ($anexos as $arquivo) {
                    $arquivosSalvos[] = $this->salvarAnexo($agendamento, $arquivo);
                }

                return $agendamento->load('servicos', 'anexos');
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($arquivosSalvos);

            throw $e;
        }
    }

    private function salvarAnexo(Agendamento $agendamento, UploadedFile $arquivo): string
    {
        $caminho = $arquivo->store("anexos/{$agendamento->id}", 'local');

        $agendamento->anexos()->create([
            'url' => $caminho,
            'nome_original' => $arquivo->getClientOriginalName(),
            'mime_type' => $arquivo->getMimeType(),
            'tamanho' => $arquivo->getSize(),
        ]);

        return $caminho;
    }

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
