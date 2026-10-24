<?php

namespace App\Http\Controllers\Disponibilidade;

use App\Http\Controllers\Controller;
use App\Http\Requests\Disponibilidade\StoreDisponibilidadeRequest;
use App\Http\Requests\Disponibilidade\UpdateDisponibilidadeRequest;
use App\Models\Disponibilidade;
use App\Services\Agendamento\DisponibilidadeService;
use Illuminate\Http\Request;

class JanelaController extends Controller
{
    public function __construct(private DisponibilidadeService $disponibilidade)
    {
    }

    public function index(Request $request)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            'data_fim' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:data_inicio'],
        ]);

        $janelas = Disponibilidade::query()
            ->when($dados['data_inicio'] ?? null, fn ($q, $d) => $q->whereDate('data', '>=', $d))
            ->when($dados['data_fim'] ?? null, fn ($q, $d) => $q->whereDate('data', '<=', $d))
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->get();

        return response()->json(['data' => $janelas]);
    }

    public function store(StoreDisponibilidadeRequest $request)
    {
        $janela = Disponibilidade::create($request->validated() + ['usuario_id' => $request->user()->id]);

        return response()->json(['data' => $janela->refresh()], 201);
    }

    public function show(Disponibilidade $disponibilidade)
    {
        return response()->json(['data' => $disponibilidade]);
    }

    public function update(UpdateDisponibilidadeRequest $request, Disponibilidade $disponibilidade)
    {
        $dados = $request->validated();

        if (isset($dados['hora_inicio']) || isset($dados['hora_fim'])) {
            $afetados = $this->disponibilidade->agendamentosAfetados(
                $disponibilidade,
                $dados['hora_inicio'] ?? substr($disponibilidade->hora_inicio, 0, 5),
                $dados['hora_fim'] ?? substr($disponibilidade->hora_fim, 0, 5)
            );

            if ($afetados->isNotEmpty()) {
                return $this->conflito($afetados);
            }
        }

        $disponibilidade->update($dados);

        return response()->json(['data' => $disponibilidade->fresh()]);
    }

    public function destroy(Disponibilidade $disponibilidade)
    {
        $afetados = $this->disponibilidade->agendamentosAfetados($disponibilidade);

        if ($afetados->isNotEmpty()) {
            return $this->conflito($afetados);
        }

        $disponibilidade->delete();

        return response()->noContent();
    }

    private function conflito($afetados)
    {
        return response()->json([
            'message' => 'A janela possui agendamentos. Cancele ou remarque-os antes.',
            'agendamentos' => $afetados->pluck('id'),
        ], 409);
    }
}
