<?php

use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Costos\SolicitudPago;

// El TC del día viene del fake global de TipoCambioService en tests/Pest.php:
// USD → 18.5, EUR → 20.0 (sin red).

test('asigna el TC del día a una solicitud en USD por folio', function () {
    $sp = SolicitudPago::factory()->create(['tipo_moneda' => 'usd', 'tipo_cambio' => 1]);

    $this->artisan('costos:asignar-tipo-cambio', ['folio' => $sp->folio])
        ->assertSuccessful();

    expect((float) $sp->fresh()->tipo_cambio)->toBe(18.5);
});

test('pregunta el folio cuando no se pasa como argumento', function () {
    $sp = SolicitudPago::factory()->create(['tipo_moneda' => 'eur', 'tipo_cambio' => 1]);

    $this->artisan('costos:asignar-tipo-cambio')
        ->expectsQuestion('Folio del documento (REQ-... o SP-...)', $sp->folio)
        ->assertSuccessful();

    expect((float) $sp->fresh()->tipo_cambio)->toBe(20.0);
});

test('detecta la divisa de una requisición desde sus cotizaciones seleccionadas', function () {
    $req = Requisicion::factory()->create(['estatus' => 'borrador', 'tipo_cambio' => 1]);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id]);
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'moneda' => 'usd',
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'proveedor_id' => $precio->proveedor_id,
    ]);

    $this->artisan('costos:asignar-tipo-cambio', ['folio' => $req->folio])
        ->assertSuccessful();

    expect((float) $req->fresh()->tipo_cambio)->toBe(18.5);
});

test('un documento en MXN no requiere TC y no se toca', function () {
    $sp = SolicitudPago::factory()->create(['tipo_moneda' => 'mxn', 'tipo_cambio' => 1]);

    $this->artisan('costos:asignar-tipo-cambio', ['folio' => $sp->folio])
        ->expectsOutputToContain('no requiere tipo de cambio')
        ->assertSuccessful();

    expect((float) $sp->fresh()->tipo_cambio)->toBe(1.0);
});

test('pide confirmación si ya había un TC capturado y respeta el no', function () {
    $sp = SolicitudPago::factory()->create(['tipo_moneda' => 'usd', 'tipo_cambio' => 19.5]);

    $this->artisan('costos:asignar-tipo-cambio', ['folio' => $sp->folio])
        ->expectsConfirmation('El documento ya tiene un TC capturado (19.500000). ¿Sobrescribirlo?', 'no')
        ->assertSuccessful();

    expect((float) $sp->fresh()->tipo_cambio)->toBe(19.5);
});

test('rechaza documentos que ya no admiten cambios', function () {
    $sp = SolicitudPago::factory()->create([
        'tipo_moneda' => 'usd',
        'tipo_cambio' => 1,
        'estatus' => 'pagada',
    ]);

    $this->artisan('costos:asignar-tipo-cambio', ['folio' => $sp->folio])
        ->assertFailed();

    expect((float) $sp->fresh()->tipo_cambio)->toBe(1.0);
});

test('dry-run muestra la tasa sin escribir', function () {
    $sp = SolicitudPago::factory()->create(['tipo_moneda' => 'usd', 'tipo_cambio' => 1]);

    $this->artisan('costos:asignar-tipo-cambio', ['folio' => $sp->folio, '--dry-run' => true])
        ->expectsOutputToContain('dry-run')
        ->assertSuccessful();

    expect((float) $sp->fresh()->tipo_cambio)->toBe(1.0);
});

test('folio inexistente falla con mensaje claro', function () {
    $this->artisan('costos:asignar-tipo-cambio', ['folio' => 'SP-999999'])
        ->expectsOutputToContain('No se encontró')
        ->assertFailed();
});
