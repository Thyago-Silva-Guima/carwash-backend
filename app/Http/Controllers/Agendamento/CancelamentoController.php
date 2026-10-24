<?php

namespace App\Http\Controllers\Agendamento;

use App\Http\Controllers\Controller;
use App\Services\Agendamento\AgendamentoService;
use Illuminate\Http\Request;

class CancelamentoController extends Controller
{
    public function __invoke(Request $request, int $id, AgendamentoService $service)
    {
        $agendamento = $request->user()->agendamentos()->findOrFail($id);

        return response()->json(['data' => $service->cancelar($agendamento)]);
    }
}
