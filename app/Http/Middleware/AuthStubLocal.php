<?php

namespace App\Http\Middleware;

use App\Models\Cliente;
use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** STUB local: simula o login por header. Na integração, trocar por auth:sanctum. */
class AuthStubLocal
{
    public function handle(Request $request, Closure $next, string $tipo = 'cliente'): Response
    {
        abort_unless(app()->environment('local'), 403, 'Stub disponível só em ambiente local.');

        $usuario = $tipo === 'usuario'
            ? Usuario::find($request->header('X-Usuario-Id'))
            : Cliente::find($request->header('X-Cliente-Id'));

        if (! $usuario) {
            return response()->json(['message' => 'Não autenticado (stub).'], 401);
        }

        $request->setUserResolver(fn () => $usuario);

        return $next($request);
    }
}
