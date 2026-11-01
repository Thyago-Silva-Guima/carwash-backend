<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AutenticaPorSenha;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    use AutenticaPorSenha;

    public function login(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $this->autenticar($request, User::class);

        $usuario->tokens()->delete();

        return response()->json([
            'token' => $usuario->createToken('admin')->plainTextToken,
            'usuario' => $this->dados($usuario),
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

    private function dados(User $usuario): array
    {
        return $usuario->only(['id', 'nome', 'email', 'perfil']);
    }
}
