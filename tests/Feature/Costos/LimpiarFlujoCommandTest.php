<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Permiso;
use App\Models\Costos\Producto;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\SolicitudPago;
use App\Models\Departamento;

test('limpiar-flujo borra el transaccional, conserva catálogos y resetea el acumulado', function () {
    $depto = Departamento::factory()->create();
    $rubro = ObraRubro::factory()->create(['acumulado' => 500]);
    $producto = Producto::factory()->create();
    $permiso = Permiso::create(['descripcion' => 'N1', 'nivel' => 1, 'tipo_aprobacion' => 'requisicion']);

    $req = Requisicion::factory()->create(['departamento_id' => $depto->id]);
    RequisicionDetalle::factory()->create(['requisicion_id' => $req->id]);
    SolicitudPago::factory()->create(['departamento_id' => $depto->id]);

    $this->artisan('costos:limpiar-flujo', ['--force' => true])->assertSuccessful();

    // Transaccional borrado.
    expect(Requisicion::count())->toBe(0)
        ->and(RequisicionDetalle::count())->toBe(0)
        ->and(SolicitudPago::count())->toBe(0);

    // Catálogos/config intactos; acumulado reseteado.
    expect(Producto::find($producto->id))->not->toBeNull()
        ->and(Permiso::find($permiso->id))->not->toBeNull()
        ->and(ObraRubro::find($rubro->id))->not->toBeNull()
        ->and((float) ObraRubro::find($rubro->id)->acumulado)->toBe(0.0);
});
