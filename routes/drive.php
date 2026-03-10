<?php

use App\Http\Controllers\Drive\DriveArchivoController;
use App\Http\Controllers\Drive\DriveAuthController;
use App\Http\Controllers\Drive\DriveDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('drive')->name('drive.')->group(function () {
    // Ruta pública — descarga por link compartido (sin auth)
    Route::get('compartido/{token}', [DriveArchivoController::class, 'compartido'])->name('compartido');

    // Guest routes
    Route::middleware('guest:externo')->group(function () {
        Route::get('login', [DriveAuthController::class, 'showLogin'])->name('login');
    });

    Route::post('login', [DriveAuthController::class, 'login'])->name('login.store');

    // Authenticated drive routes
    Route::middleware('auth:externo')->group(function () {
        Route::post('logout', [DriveAuthController::class, 'logout'])->name('logout');
        Route::get('/', [DriveDashboardController::class, 'index'])->name('dashboard');
        Route::get('carpetas/{carpeta}', [DriveArchivoController::class, 'index'])->name('carpetas.show');
        Route::post('archivos', [DriveArchivoController::class, 'store'])->name('archivos.store');
        Route::get('archivos/{archivo}/descargar', [DriveArchivoController::class, 'show'])->name('archivos.descargar');
        Route::delete('archivos/{archivo}', [DriveArchivoController::class, 'destroy'])->name('archivos.destroy');
    });
});
