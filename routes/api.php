<?php

use App\Http\Controllers\Api\Cal\AuthController;
use App\Http\Controllers\Api\Cal\EtapaController;
use App\Http\Controllers\Api\Cal\FlechaController;
use App\Http\Controllers\Api\Cal\ObraController;
use App\Http\Controllers\Api\Cal\PiezaController;
use App\Http\Controllers\Api\Cal\PiezaPlanoController;
use App\Http\Controllers\Api\Cal\ReporteController;
use App\Http\Controllers\Api\Cal\SoldadorController;
use App\Http\Controllers\Api\Cal\UserController;
use Illuminate\Support\Facades\Route;

//Route::get('/ping', fn () => response()->json(['status' => 'ok']));

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [AuthController::class, 'user']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::apiResource('obras', ObraController::class)->only(['index', 'show']);
    Route::apiResource('etapas', EtapaController::class)->parameters(['etapas' => 'etapa']);
    Route::apiResource('piezas', PiezaController::class)->parameters(['piezas' => 'pieza']);
    Route::apiResource('piezas-planos', PiezaPlanoController::class)->parameters(['piezas-planos' => 'piezaPlano']);

    Route::apiResource('reportes', ReporteController::class)->parameters(['reportes' => 'reporte']);
    Route::get('reportes/{reporte}/pdf', [ReporteController::class, 'pdf'])->name('api.reportes.pdf');
    Route::post('reportes/{reporte}/copiar', [ReporteController::class, 'copiar'])->name('api.reportes.copiar');
    Route::delete('reportes/{reporte}/flechas', [ReporteController::class, 'deleteFlechas'])->name('api.reportes.delete-flechas');

    Route::apiResource('flechas', FlechaController::class)->parameters(['flechas' => 'flecha']);
    Route::apiResource('soldadores', SoldadorController::class)->parameters(['soldadores' => 'soldador']);
    Route::apiResource('users', UserController::class);
});
