<?php

namespace App\Http\Controllers\Agendamento;

use App\Events\AgendamentoCancelado;
use App\Events\AgendamentoConfirmado;
use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminAgendamentoController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in([
                Agendamento::STATUS_PENDENTE,
                Agendamento::STATUS_CONFIRMADO,
                Agendamento::STATUS_CONCLUIDO,
                Agendamento::STATUS_CANCELADO,
            ])],
            'data_inicio' => ['sometimes', 'date_format:Y-m-d'],
            'data_fim' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:data_inicio'],
            'cliente_id' => ['sometimes', 'integer', 'exists:cliente,id'],
            'servico_id' => ['sometimes', 'integer', 'exists:servico,id'],
            'usuario_id' => ['sometimes', 'integer', 'exists:usuario,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $agendamentos = Agendamento::query()
            ->with(['cliente', 'endereco', 'servicos', 'atendente', 'anexos'])
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['data_inicio'] ?? null, fn (Builder $query, $date) => $query->whereDate('data_hora', '>=', $date))
            ->when($filters['data_fim'] ?? null, fn (Builder $query, $date) => $query->whereDate('data_hora', '<=', $date))
            ->when($filters['cliente_id'] ?? null, fn (Builder $query, $id) => $query->where('cliente_id', $id))
            ->when($filters['usuario_id'] ?? null, fn (Builder $query, $id) => $query->where('usuario_id', $id))
            ->when($filters['servico_id'] ?? null, fn (Builder $query, $id) => $query->whereHas(
                'servicos',
                fn (Builder $services) => $services->where('servico.id', $id)
            ))
            ->orderByDesc('data_hora')
            ->paginate($filters['per_page'] ?? 15)
            ->through(fn (Agendamento $agendamento) => $this->comAnexos($agendamento));

        return response()->json($agendamentos);
    }

    public function resumo()
    {
        return response()->json([
            'data' => [
                'pendentes' => Agendamento::where('status', Agendamento::STATUS_PENDENTE)->count(),
            ],
        ]);
    }

    public function show(int $id)
    {
        $agendamento = Agendamento::query()
            ->with(['cliente', 'endereco', 'servicos', 'atendente', 'anexos'])
            ->findOrFail($id);

        return response()->json(['data' => $this->comAnexos($agendamento)]);
    }

    public function confirmar(Request $request, int $id)
    {
        $agendamento = DB::transaction(function () use ($request, $id) {
            $agendamento = Agendamento::query()->lockForUpdate()->findOrFail($id);

            abort_unless(
                $agendamento->status === Agendamento::STATUS_PENDENTE,
                409,
                'Somente agendamentos pendentes podem ser confirmados.'
            );

            $agendamento->update([
                'status' => Agendamento::STATUS_CONFIRMADO,
                'usuario_id' => $request->user()->id,
            ]);

            return $agendamento->fresh();
        });

        AgendamentoConfirmado::dispatch($agendamento);

        return response()->json(['data' => $agendamento->load(['cliente', 'endereco', 'servicos', 'atendente'])]);
    }

    public function recusar(Request $request, int $id)
    {
        $dados = $request->validate([
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        $agendamento = DB::transaction(function () use ($dados, $id) {
            $agendamento = Agendamento::query()->lockForUpdate()->findOrFail($id);

            abort_unless(
                $agendamento->status === Agendamento::STATUS_PENDENTE,
                409,
                'Somente agendamentos pendentes podem ser recusados.'
            );

            $agendamento->update([
                'status' => Agendamento::STATUS_CANCELADO,
                'motivo' => $dados['motivo'],
            ]);

            return $agendamento->fresh();
        });

        AgendamentoCancelado::dispatch($agendamento);

        return response()->json(['data' => $agendamento]);
    }

    public function concluir(int $id)
    {
        $agendamento = DB::transaction(function () use ($id) {
            $agendamento = Agendamento::query()->lockForUpdate()->findOrFail($id);

            abort_unless(
                $agendamento->status === Agendamento::STATUS_CONFIRMADO,
                409,
                'Somente agendamentos confirmados podem ser concluídos.'
            );

            $agendamento->update(['status' => Agendamento::STATUS_CONCLUIDO]);

            return $agendamento->fresh();
        });

        return response()->json(['data' => $agendamento]);
    }

    private function comAnexos(Agendamento $agendamento): array
    {
        return array_merge($agendamento->toArray(), [
            'anexos' => $agendamento->anexos->map->paraResposta()->all(),
        ]);
    }
}