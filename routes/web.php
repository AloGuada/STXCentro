<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('dashboard');
})->middleware(['auth', 'verified'])->name('home');

Route::get('dashboard', function () {
    return Inertia::render('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/admin.php';
// require __DIR__.'/intra.php'; // deshabilitado: intranet pública con documentos ISO
require __DIR__.'/rh.php';
require __DIR__.'/sti.php';
require __DIR__.'/portal.php';
require __DIR__.'/drive.php';
