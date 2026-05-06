<?php

use App\Http\Controllers\Sti\TicketPublicoController;
use Illuminate\Support\Facades\Route;

// Rutas STI públicas: cualquier usuario puede levantar un ticket sin login
// (se restringe por IP en nginx — ver location ^~ /sti/reportes/)
Route::prefix('sti/reportes')->name('sti.reportes.')->group(function () {
    Route::get('/tickets/nuevo', [TicketPublicoController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketPublicoController::class, 'store'])->name('tickets.store');
});

// Rutas STI autenticadas: ver listado, detalle y comentar
Route::middleware(['auth', 'verified'])->prefix('sti')->name('sti.')->group(function () {
    Route::get('/tickets', [TicketPublicoController::class, 'index'])->name('ticket.index');
    Route::get('/tickets/{ticket}', [TicketPublicoController::class, 'show'])->name('ticket.show');
    Route::post('/tickets/{ticket}/comentario', [TicketPublicoController::class, 'storeComentario'])->name('ticket.comentario.store');
});
