<?php

use App\Http\Controllers\Api\Cal\AuthController;
use App\Http\Controllers\Api\Cal\EtapaController;
use App\Http\Controllers\Api\Cal\FlechaController;
use App\Http\Controllers\Api\Cal\ObraController;
use App\Http\Controllers\Api\Cal\PiezaController;
use App\Http\Controllers\Api\Cal\PiezaPlanoController;
use App\Http\Controllers\Api\Cal\ReporteController;
use App\Http\Controllers\Api\Cal\ReportePdfController;
use App\Http\Controllers\Api\Cal\SoldadorController;
use App\Http\Controllers\Api\Cal\UserController;
use Illuminate\Support\Facades\Route;

// Route::get('/ping', fn () => response()->json(['status' => 'ok']));

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [AuthController::class, 'user']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::apiResource('obras', ObraController::class);
    Route::apiResource('etapas', EtapaController::class)->parameters(['etapas' => 'etapa']);
    Route::apiResource('piezas', PiezaController::class)->parameters(['piezas' => 'pieza']);
    Route::get('piezas-planos/{piezaId}/planos', [PiezaPlanoController::class, 'index']);
    Route::post('piezas-planos', [PiezaPlanoController::class, 'store']);
    Route::get('piezas-planos/{piezaPlano}', [PiezaPlanoController::class, 'show']);
    Route::put('piezas-planos/{piezaPlano}', [PiezaPlanoController::class, 'update']);
    Route::delete('piezas-planos/{piezaPlano}', [PiezaPlanoController::class, 'destroy']);

    Route::apiResource('reportes', ReporteController::class)->parameters(['reportes' => 'reporte']);
    Route::get('reportes/{reporte}/pdf', [ReportePdfController::class, 'generarReporte'])->name('api.reportes.pdf');
    Route::post('reportes/{reporte}/copiar', [ReporteController::class, 'copiar'])->name('api.reportes.copiar');
    Route::delete('reportes/{reporte}/flechas', [ReporteController::class, 'deleteFlechas'])->name('api.reportes.delete-flechas');

    Route::apiResource('flechas', FlechaController::class)->parameters(['flechas' => 'flecha']);
    Route::apiResource('soldadores', SoldadorController::class)->parameters(['soldadores' => 'soldador']);
    Route::apiResource('users', UserController::class);
});
