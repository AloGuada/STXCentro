<?php

use App\Http\Controllers\Sti\TicketPublicoController;
use Illuminate\Support\Facades\Route;

// Rutas públicas STI
Route::prefix('sti')->name('sti.')->group(function () {
    Route::get('/tickets', [TicketPublicoController::class, 'index'])->name('ticket.index');
    Route::get('/ticket/nuevo', [TicketPublicoController::class, 'create'])->name('ticket.create');
    Route::post('/ticket', [TicketPublicoController::class, 'store'])->name('ticket.store');
    Route::get('/ticket/{ticket}', [TicketPublicoController::class, 'show'])->name('ticket.show');
    Route::post('/ticket/{ticket}/comentario', [TicketPublicoController::class, 'storeComentario'])->name('ticket.comentario.store');
});
