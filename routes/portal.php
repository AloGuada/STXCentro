<?php

use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalComplementoPagoController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\Portal\PortalFacturaController;
use App\Http\Controllers\Portal\PortalNotaCreditoController;
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

        Route::post('facturas/preview', [PortalFacturaController::class, 'previewXml'])->name('facturas.preview.store');
        Route::get('facturas/preview', [PortalFacturaController::class, 'preview'])->name('facturas.preview');
        Route::post('facturas/cancel-preview', [PortalFacturaController::class, 'cancelPreview'])->name('facturas.cancel-preview');

        Route::resource('facturas', PortalFacturaController::class)
            ->only(['index', 'store', 'show'])
            ->parameters(['facturas' => 'factura']);

        Route::post('facturas/{factura}/comprobante', [PortalFacturaController::class, 'subirComprobante'])
            ->name('facturas.comprobante');

        Route::post('notas-credito', [PortalNotaCreditoController::class, 'store'])
            ->name('notas-credito.store');

        Route::get('complementos', [PortalComplementoPagoController::class, 'index'])->name('complementos.index');
        Route::post('complementos', [PortalComplementoPagoController::class, 'store'])->name('complementos.store');

        Route::resource('pagos', PortalPagoController::class)
            ->only(['index', 'show'])
            ->parameters(['pagos' => 'pago']);
    });
});
