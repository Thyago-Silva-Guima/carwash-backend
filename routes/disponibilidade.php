<?php

use App\Http\Controllers\Agendamento\AgendamentoController;
use App\Http\Controllers\Agendamento\CancelamentoController;
use App\Http\Controllers\Disponibilidade\HorariosController;
use App\Http\Controllers\Disponibilidade\JanelaController;
use App\Http\Controllers\Disponibilidade\LoteController;
use Illuminate\Support\Facades\Route;

// Cliente logado
Route::middleware('auth.stub:cliente')->group(function () {
    Route::get('/disponibilidade/dias', [HorariosController::class, 'dias']);
    Route::get('/disponibilidade', [HorariosController::class, 'horarios']);

    Route::get('/agendamentos', [AgendamentoController::class, 'index']);
    Route::post('/agendamentos', [AgendamentoController::class, 'store']);
    Route::get('/agendamentos/{id}', [AgendamentoController::class, 'show']);
    Route::patch('/agendamentos/{id}/cancelar', CancelamentoController::class);
});

// Administrador
Route::prefix('admin')->middleware(['auth.stub:usuario', 'admin'])->group(function () {
    Route::post('disponibilidades/lote', LoteController::class);
    Route::apiResource('disponibilidades', JanelaController::class)
        ->parameters(['disponibilidades' => 'disponibilidade']);
});
