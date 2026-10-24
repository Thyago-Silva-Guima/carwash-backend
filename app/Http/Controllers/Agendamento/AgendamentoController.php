<?php

namespace App\Http\Controllers\Agendamento;

use App\Http\Controllers\Controller;
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

    // Busca só entre os agendamentos do próprio cliente: o de outro cliente dá 404.
    public function show(Request $request, int $id)
    {
        $agendamento = $request->user()->agendamentos()
            ->with(['servicos', 'endereco'])
            ->findOrFail($id);

        return response()->json(['data' => $agendamento]);
    }

    public function store(StoreAgendamentoRequest $request)
    {
        $agendamento = $this->agendamentos->criar($request->user(), $request->validated());

        return response()->json(['data' => $agendamento->load('servicos')], 201);
    }
}
