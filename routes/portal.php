<?php

use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalComplementoPagoController;
use App\Http\Controllers\Portal\PortalContrareciboController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\Portal\PortalFacturaController;
use App\Http\Controllers\Portal\PortalMediaController;
use App\Http\Controllers\Portal\PortalNotaCreditoController;
use App\Http\Controllers\Portal\PortalOrdenCompraController;
use App\Http\Controllers\Portal\PortalPagoController;
use App\Http\Controllers\Portal\PortalTableroController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::prefix('portal')->name('portal.')->group(function () {
    /** Prototipo de portal simplificado (una sola tabla, sin backend ni auth). */
    Route::get('prueba', fn () => Inertia::render('portal/prueba/tablero'))->name('prueba');

    // Guest routes
    Route::middleware('guest:proveedor')->group(function () {
        Route::get('login', [PortalAuthController::class, 'showLogin'])->name('login');
    });

    Route::post('login', [PortalAuthController::class, 'login'])->name('login.store');

    // Authenticated portal routes
    Route::middleware('auth:proveedor')->group(function () {
        Route::post('logout', [PortalAuthController::class, 'logout'])->name('logout');

        /**
         * El tablero (una sola tabla) es la entrada del portal. Las pantallas
         * anteriores siguen disponibles en sus propias rutas mientras se decide
         * si se retiran.
         */
        Route::get('/', [PortalTableroController::class, 'index'])->name('tablero');
        Route::redirect('tablero', '/portal');

        Route::get('dashboard', [PortalDashboardController::class, 'index'])->name('dashboard');

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

        Route::get('facturas/{factura}/contrarecibo', [PortalContrareciboController::class, 'show'])
            ->name('facturas.contrarecibo');

        Route::get('media/{media}', [PortalMediaController::class, 'show'])->name('media.show');

        Route::post('notas-credito', [PortalNotaCreditoController::class, 'store'])
            ->name('notas-credito.store');

        Route::get('complementos', [PortalComplementoPagoController::class, 'index'])->name('complementos.index');
        Route::post('complementos', [PortalComplementoPagoController::class, 'store'])->name('complementos.store');

        Route::resource('pagos', PortalPagoController::class)
            ->only(['index', 'show'])
            ->parameters(['pagos' => 'pago']);
    });
});
