<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AutenticaPorSenha;
use App\Http\Requests\RegistrarClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteAuthController extends Controller
{
    use AutenticaPorSenha;

    public function register(RegistrarClienteRequest $request): JsonResponse
    {
        $dados = $request->validated();

        $cliente = Cliente::create([
            'nome' => $dados['nome'],
            'telefone' => $dados['telefone'],
            'email' => $dados['email'],
            'senha_hash' => $dados['password'],
            'aceite_termos_em' => now(),
        ]);

        return response()->json([
            'token' => $cliente->createToken('cliente')->plainTextToken,
            'cliente' => $this->dados($cliente),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        /** @var Cliente $cliente */
        $cliente = $this->autenticar($request, Cliente::class);

        $cliente->tokens()->delete();

        return response()->json([
            'token' => $cliente->createToken('cliente')->plainTextToken,
            'cliente' => $this->dados($cliente),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->dados($request->user()));
    }

    private function dados(Cliente $cliente): array
    {
        return $cliente->only(['id', 'nome', 'email', 'telefone']);
    }
}
