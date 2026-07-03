<?php

use App\Models\Cal\PiezaPlano;
use App\Models\Cal\Reporte;
use App\Models\Costos\OrdenCompra;

test('convierte folio de costos a año de 2 dígitos', function () {
    $oc = OrdenCompra::factory()->create(['folio' => 'OC-20260401']);

    $this->artisan('folios:normalizar-anio --apply')->assertSuccessful();

    expect($oc->fresh()->folio)->toBe('OC-260401');
});

test('el dry-run no modifica nada', function () {
    $oc = OrdenCompra::factory()->create(['folio' => 'OC-20260401']);

    $this->artisan('folios:normalizar-anio')->assertSuccessful();

    expect($oc->fresh()->folio)->toBe('OC-20260401');
});

test('omite la conversión cuando el folio destino ya existe (colisión)', function () {
    $viejo = OrdenCompra::factory()->create(['folio' => 'OC-20260701']);
    $nuevo = OrdenCompra::factory()->create(['folio' => 'OC-260701']);

    $this->artisan('folios:normalizar-anio --apply')->assertFailed();

    // El viejo se queda intacto (no se sobrescribe el destino existente).
    expect($viejo->fresh()->folio)->toBe('OC-20260701');
    expect($nuevo->fresh()->folio)->toBe('OC-260701');
});

test('convierte folio de reporte de calidad y respeta plantillas', function () {
    $plano = PiezaPlano::factory()->create();
    $reporte = Reporte::factory()->create(['plano_id' => $plano->id, 'es_plantilla' => false, 'folio' => 'IV20260401']);
    $plantilla = Reporte::factory()->plantilla()->create(['plano_id' => $plano->id]);

    $this->artisan('folios:normalizar-anio --apply')->assertSuccessful();

    expect($reporte->fresh()->folio)->toBe('IV260401');
    expect($plantilla->fresh()->folio)->toBeNull();
});

test('es idempotente: no vuelve a tocar folios ya convertidos', function () {
    $oc = OrdenCompra::factory()->create(['folio' => 'OC-260401']);

    $this->artisan('folios:normalizar-anio --apply')->assertSuccessful();

    expect($oc->fresh()->folio)->toBe('OC-260401');
});
