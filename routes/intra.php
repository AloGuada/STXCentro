<?php

use App\Http\Controllers\Intra\AreaController;
use App\Http\Controllers\Intra\DocumentoController;
use App\Http\Controllers\Intra\HomeController;
use App\Http\Controllers\Intra\SeccionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Intranet Public Routes
|--------------------------------------------------------------------------
|
| These routes are publicly accessible without authentication.
| They provide read-only access to ISO documentation and procedures.
|
*/

Route::prefix('intra')->name('intra.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/est/{seccion:slug}', [SeccionController::class, 'show'])->name('seccion');
    Route::get('/area/{area}', [AreaController::class, 'show'])->name('area');
    Route::get('/doc/{documento}', [DocumentoController::class, 'show'])->name('documento');
});
