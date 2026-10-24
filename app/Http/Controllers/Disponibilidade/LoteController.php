<?php

namespace App\Http\Controllers\Disponibilidade;

use App\Http\Controllers\Controller;
use App\Http\Requests\Disponibilidade\LoteDisponibilidadeRequest;
use App\Services\Agendamento\DisponibilidadeService;

class LoteController extends Controller
{
    public function __invoke(LoteDisponibilidadeRequest $request, DisponibilidadeService $service)
    {
        $resultado = $service->criarEmLote($request->validated(), $request->user()->id);

        return response()->json(['data' => $resultado], 201);
    }
}
