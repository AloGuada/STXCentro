<?php

use App\Http\Controllers\Sti\TicketPublicoController;
use Illuminate\Support\Facades\Route;

// Rutas STI públicas: cualquier usuario puede levantar un ticket sin login
Route::prefix('sti')->name('sti.')->group(function () {
    Route::get('/ticket/nuevo', [TicketPublicoController::class, 'create'])->name('ticket.create');
    Route::post('/ticket', [TicketPublicoController::class, 'store'])->name('ticket.store');
});

// Rutas STI autenticadas: ver listado, detalle y comentar
Route::middleware(['auth', 'verified'])->prefix('sti')->name('sti.')->group(function () {
    Route::get('/tickets', [TicketPublicoController::class, 'index'])->name('ticket.index');
    Route::get('/ticket/{ticket}', [TicketPublicoController::class, 'show'])->name('ticket.show');
    Route::post('/ticket/{ticket}/comentario', [TicketPublicoController::class, 'storeComentario'])->name('ticket.comentario.store');
});
