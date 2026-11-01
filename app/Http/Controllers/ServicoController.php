<?php

namespace App\Http\Controllers;

use App\Models\Servico;
use Illuminate\Http\JsonResponse;

class ServicoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Servico::where('ativo', true)
                ->orderBy('id')
                ->get(['id', 'nome', 'descricao', 'categoria', 'duracao_minutos', 'preco_base'])
        );
    }
}
