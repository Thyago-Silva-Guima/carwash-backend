<?php

namespace App\Http\Controllers\Disponibilidade;

use App\Http\Controllers\Controller;
use App\Models\Servico;
use App\Services\Agendamento\DisponibilidadeService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HorariosController extends Controller
{
    public function __construct(private DisponibilidadeService $disponibilidade)
    {
    }

    /** GET /disponibilidade/dias?mes=2026-11&servico_id=3 */
    public function dias(Request $request)
    {
        $dados = $request->validate([
            'mes' => ['required', 'date_format:Y-m'],
            'servico_id' => ['required', 'integer', 'exists:servico,id'],
        ]);

        $duracao = Servico::findOrFail($dados['servico_id'])->duracao_minutos;

        return response()->json([
            'data' => $this->disponibilidade->diasComVaga($dados['mes'], $duracao),
        ]);
    }

    /** GET /disponibilidade?data=2026-11-14&servico_id=3 */
    public function horarios(Request $request)
    {
        $dados = $request->validate([
            'data' => ['required', 'date_format:Y-m-d'],
            'servico_id' => ['required', 'integer', 'exists:servico,id'],
        ]);

        $duracao = Servico::findOrFail($dados['servico_id'])->duracao_minutos;

        return response()->json([
            'data' => $this->disponibilidade->horariosLivres(Carbon::parse($dados['data']), $duracao),
        ]);
    }
}
