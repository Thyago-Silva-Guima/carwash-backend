<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApenasAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Usuario || ! $user->ehAdmin()) {
            return response()->json(['message' => 'Acesso restrito ao administrador.'], 403);
        }

        return $next($request);
    }
}
