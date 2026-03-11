<?php

use App\Http\Controllers\Rh\PermisoPublicoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RH Public Routes
|--------------------------------------------------------------------------
|
| These routes are publicly accessible without authentication.
| They provide employee self-service forms (permisos de ausencia, etc.).
|
*/

Route::prefix('rh')->name('rh.publico.')->group(function (): void {
    Route::get('/permisos', [PermisoPublicoController::class, 'index'])->name('permisos.index');
    Route::post('/permisos', [PermisoPublicoController::class, 'store'])->name('permisos.store');
    Route::get('/permisos/{permisoAusencia}/pdf', [PermisoPublicoController::class, 'pdf'])->name('permisos.pdf');
});
