<?php

namespace App\Http\Controllers\Agendamento;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Http\Requests\Agendamento\StoreAgendamentoRequest;
use App\Services\Agendamento\AgendamentoService;
use Illuminate\Http\Request;

class AgendamentoController extends Controller
{
    public function __construct(private AgendamentoService $agendamentos)
    {
    }

    public function index(Request $request)
    {
        $lista = $request->user()->agendamentos()
            ->with(['servicos', 'endereco'])
            ->orderByDesc('data_hora')
            ->get();

        return response()->json(['data' => $lista]);
    }

    public function show(Request $request, int $id)
    {
        $agendamento = $request->user()->agendamentos()
            ->with(['servicos', 'endereco', 'anexos'])
            ->findOrFail($id);

        return response()->json(['data' => $this->comAnexos($agendamento)]);
    }

    public function store(StoreAgendamentoRequest $request)
    {
        $agendamento = $this->agendamentos->criar($request->user(), $request->validated());

        return response()->json(['data' => $this->comAnexos($agendamento)], 201);
    }

    private function comAnexos(Agendamento $agendamento): array
    {
        return array_merge($agendamento->toArray(), [
            'anexos' => $agendamento->anexos->map->paraResposta()->all(),
        ]);
    }
}
