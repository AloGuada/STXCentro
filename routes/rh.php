<?php

use App\Http\Controllers\Rh\PermisoPublicoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RH Routes (autenticadas)
|--------------------------------------------------------------------------
|
| Formularios de autoservicio para empleados (permisos de ausencia, etc.).
| Requieren sesión iniciada.
|
*/

Route::middleware(['auth', 'verified'])->prefix('rh')->name('rh.publico.')->group(function (): void {
    Route::get('/permisos', [PermisoPublicoController::class, 'index'])->name('permisos.index');
    Route::post('/permisos', [PermisoPublicoController::class, 'store'])->name('permisos.store');
    Route::get('/permisos/{permisoAusencia}/pdf', [PermisoPublicoController::class, 'pdf'])->name('permisos.pdf');
});
