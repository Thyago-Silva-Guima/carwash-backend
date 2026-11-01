<?php

namespace App\Http\Middleware;

use App\Models\Cliente;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExigePerfil
{
    public function handle(Request $request, Closure $next, string $perfil): Response
    {
        $usuario = $request->user();

        $permitido = match ($perfil) {
            'cliente' => $usuario instanceof Cliente,
            'admin' => $usuario instanceof User && $usuario->ehAdmin(),
            'equipe' => $usuario instanceof User && $usuario->ehEquipe(),
            default => false,
        };

        if (! $permitido) {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }

        return $next($request);
    }
}
