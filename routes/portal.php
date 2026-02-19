<?php

use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\Portal\PortalFacturaController;
use App\Http\Controllers\Portal\PortalOrdenCompraController;
use App\Http\Controllers\Portal\PortalPagoController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('portal.')->group(function () {
    // Guest routes
    Route::middleware('guest:proveedor')->group(function () {
        Route::get('login', [PortalAuthController::class, 'showLogin'])->name('login');
    });

    Route::post('login', [PortalAuthController::class, 'login'])->name('login.store');

    // Authenticated portal routes
    Route::middleware('auth:proveedor')->group(function () {
        Route::post('logout', [PortalAuthController::class, 'logout'])->name('logout');
        Route::get('/', [PortalDashboardController::class, 'index'])->name('dashboard');

        Route::resource('ordenes-compra', PortalOrdenCompraController::class)
            ->only(['index', 'show'])
            ->parameters(['ordenes-compra' => 'ordenCompra']);

        Route::resource('facturas', PortalFacturaController::class)
            ->only(['index', 'store', 'show'])
            ->parameters(['facturas' => 'factura']);

        Route::resource('pagos', PortalPagoController::class)
            ->only(['index', 'show'])
            ->parameters(['pagos' => 'pago']);
    });
});
