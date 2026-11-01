<?php

use App\Http\Middleware\ExigePerfil;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'perfil' => ExigePerfil::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (AuthenticationException $e, Request $request) =>
            response()->json(['message' => 'Não autenticado.'], 401));

        $exceptions->render(fn (InvalidSignatureException $e, Request $request) =>
            response()->json(['message' => 'Link inválido ou expirado.'], 403));

        $exceptions->render(fn (NotFoundHttpException $e, Request $request) =>
            response()->json(['message' => 'Recurso não encontrado.'], 404));

        $exceptions->render(fn (MethodNotAllowedHttpException $e, Request $request) =>
            response()->json(['message' => 'Método não permitido.'], 405, $e->getHeaders()));

        $exceptions->render(fn (PostTooLargeException $e, Request $request) =>
            response()->json(['message' => 'Os arquivos enviados excedem o tamanho máximo permitido.'], 413));

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            $headers = $e->getHeaders();

            return response()->json([
                'message' => 'Muitas tentativas. Tente novamente em instantes.',
                'retry_after' => isset($headers['Retry-After']) ? (int) $headers['Retry-After'] : null,
            ], 429, $headers);
        });
    })->create();
