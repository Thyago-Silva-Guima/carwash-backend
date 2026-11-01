<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnderecoRequest;
use App\Models\Endereco;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EnderecoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $enderecos = $request->user()->enderecos()->orderBy('id')->get();

        return response()->json($enderecos->map(fn (Endereco $e) => $this->dados($e))->values());
    }

    public function store(EnderecoRequest $request): JsonResponse
    {
        $endereco = $request->user()->enderecos()->create(
            $request->validated() + ['complemento' => null]
        );

        return response()->json($this->dados($endereco), 201);
    }

    public function show(Request $request, string $endereco): JsonResponse
    {
        return response()->json($this->dados($this->buscar($request, $endereco)));
    }

    public function update(EnderecoRequest $request, string $endereco): JsonResponse
    {
        $endereco = $this->buscar($request, $endereco);

        $endereco->update($request->validated() + ['complemento' => null]);

        return response()->json($this->dados($endereco));
    }

    public function destroy(Request $request, string $endereco): JsonResponse|Response
    {
        $endereco = $this->buscar($request, $endereco);

        if ($endereco->agendamentos()->exists()) {
            return response()->json([
                'message' => 'Este endereço já foi usado em agendamentos e não pode ser removido.',
            ], 409);
        }

        $endereco->delete();

        return response()->noContent();
    }

    private function buscar(Request $request, string $id): Endereco
    {
        return $request->user()->enderecos()->findOrFail($id);
    }

    private function dados(Endereco $endereco): array
    {
        return [
            'id' => $endereco->id,
            'rua' => $endereco->rua,
            'numero' => $endereco->numero,
            'bairro' => $endereco->bairro,
            'complemento' => $endereco->complemento,
            'cidade' => $endereco->cidade,
            'cep' => $endereco->cep,
            'endereco_completo' => $endereco->endereco_completo,
        ];
    }
}