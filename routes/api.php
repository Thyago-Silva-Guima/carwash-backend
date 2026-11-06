<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\ClienteAuthController;
use App\Http\Controllers\EnderecoController;
use App\Http\Controllers\ServicoController;
use Illuminate\Support\Facades\Route;

Route::get('/servicos', [ServicoController::class, 'index']);

Route::prefix('auth')->group(function () {
    Route::post('/register', [ClienteAuthController::class, 'register'])->middleware('throttle:registro');
    Route::post('/login', [ClienteAuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'perfil:cliente'])->group(function () {
        Route::post('/logout', [ClienteAuthController::class, 'logout']);
        Route::get('/me', [ClienteAuthController::class, 'me']);
        Route::put('/me', [ClienteAuthController::class, 'atualizar']);
    });
});

Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'perfil:equipe'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'perfil:cliente'])->prefix('enderecos')->group(function () {
    Route::get('/', [EnderecoController::class, 'index']);
    Route::post('/', [EnderecoController::class, 'store']);
    Route::get('/{endereco}', [EnderecoController::class, 'show'])->whereNumber('endereco');
    Route::put('/{endereco}', [EnderecoController::class, 'update'])->whereNumber('endereco');
    Route::delete('/{endereco}', [EnderecoController::class, 'destroy'])->whereNumber('endereco');
});

// Disponibilidade, agendamentos e painel de pedidos (módulos dos Devs B e C)
require __DIR__.'/agendamento.php';
