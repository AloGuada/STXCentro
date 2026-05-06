<?php

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
