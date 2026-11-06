<?php

use App\Http\Controllers\Agendamento\AdminAgendamentoController;
use App\Http\Controllers\Agendamento\AgendamentoController;
use App\Http\Controllers\Agendamento\AnexoController;
use App\Http\Controllers\Agendamento\CancelamentoController;
use App\Http\Controllers\Disponibilidade\HorariosController;
use App\Http\Controllers\Disponibilidade\JanelaController;
use App\Http\Controllers\Disponibilidade\LoteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'perfil:cliente'])->group(function () {
    Route::get('/disponibilidade/dias', [HorariosController::class, 'dias']);
    Route::get('/disponibilidade', [HorariosController::class, 'horarios']);

    Route::get('/agendamentos', [AgendamentoController::class, 'index']);
    Route::post('/agendamentos', [AgendamentoController::class, 'store']);
    Route::get('/agendamentos/{id}', [AgendamentoController::class, 'show']);
    Route::patch('/agendamentos/{id}/cancelar', CancelamentoController::class);
});

Route::get('/anexos/{anexo}', [AnexoController::class, 'show'])
    ->whereNumber('anexo')
    ->middleware('signed')
    ->name('anexos.show');

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:sanctum', 'perfil:equipe'])->group(function () {
        Route::get('agendamentos/resumo', [AdminAgendamentoController::class, 'resumo']);
        Route::get('agendamentos', [AdminAgendamentoController::class, 'index']);
        Route::get('agendamentos/{id}', [AdminAgendamentoController::class, 'show'])->whereNumber('id');
        Route::patch('agendamentos/{id}/confirmar', [AdminAgendamentoController::class, 'confirmar'])->whereNumber('id');
        Route::patch('agendamentos/{id}/recusar', [AdminAgendamentoController::class, 'recusar'])->whereNumber('id');
        Route::patch('agendamentos/{id}/concluir', [AdminAgendamentoController::class, 'concluir'])->whereNumber('id');
    });

    Route::middleware(['auth:sanctum', 'perfil:admin'])->group(function () {
        Route::post('disponibilidades/lote', LoteController::class);
        Route::apiResource('disponibilidades', JanelaController::class)
            ->parameters(['disponibilidades' => 'disponibilidade']);
    });
});
