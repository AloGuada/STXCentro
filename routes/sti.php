<?php

use App\Http\Controllers\Admin\Sti\AsignacionActivoController;
use App\Http\Controllers\Sti\TicketPublicoController;
use Illuminate\Support\Facades\Route;

// Rutas STI públicas para reportar y dar seguimiento a tickets sin login.
// El acceso se restringe a la LAN por IP en nginx (location ^~ /sti/reportes/).
Route::prefix('sti/reportes')->name('sti.reportes.')->group(function () {
    Route::get('/tickets', [TicketPublicoController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/nuevo', [TicketPublicoController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketPublicoController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketPublicoController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/comentario', [TicketPublicoController::class, 'storeComentario'])->name('tickets.comentario.store');
});

// Firma remota de asignaciones: el empleado entra sin login con el enlace que le
// comparte TI. El token aleatorio de la URL es lo único que identifica la asignación.
Route::prefix('sti/firmas')->name('sti.firmas.')->group(function () {
    Route::get('/{asignacion_activo:firma_token}', [AsignacionActivoController::class, 'createSign'])->name('create');
    Route::post('/{asignacion_activo:firma_token}', [AsignacionActivoController::class, 'storeSign'])->middleware('throttle:10,1')->name('store');
    Route::get('/{asignacion_activo:firma_token}/pdf', [AsignacionActivoController::class, 'pdfSign'])->name('pdf');
});
