<?php

use App\Enums\ProveedorEstatus;
use App\Models\Proveedor;

test('aprueba en bloque los proveedores pendientes y deja intactos los activos y rechazados', function () {
    $pendiente = Proveedor::factory()->create(['estatus' => ProveedorEstatus::PendienteValidacion, 'activo' => false]);
    $activo = Proveedor::factory()->create(['estatus' => ProveedorEstatus::Activo, 'activo' => true]);
    $rechazado = Proveedor::factory()->create(['estatus' => ProveedorEstatus::Rechazado, 'activo' => false]);

    $this->artisan('costos:aprobar-proveedores', ['--force' => true])->assertSuccessful();

    $pendiente->refresh();
    expect($pendiente->estatus)->toBe(ProveedorEstatus::Activo)
        ->and($pendiente->activo)->toBeTrue()
        ->and($pendiente->validado_at)->not->toBeNull();

    expect($rechazado->fresh()->estatus)->toBe(ProveedorEstatus::Rechazado);
    expect($activo->fresh()->estatus)->toBe(ProveedorEstatus::Activo);
});

test('con --incluir-rechazados también activa los rechazados', function () {
    $rechazado = Proveedor::factory()->create(['estatus' => ProveedorEstatus::Rechazado, 'activo' => false]);

    $this->artisan('costos:aprobar-proveedores', ['--force' => true, '--incluir-rechazados' => true])->assertSuccessful();

    expect($rechazado->fresh()->estatus)->toBe(ProveedorEstatus::Activo);
});
