<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Costos\UsoCfdi;
use App\Models\Departamento;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Requisición cotizada y COMPLETA (3 proveedores, selección al 100% con precio
 * y una OC), lista para marcar la verificación gerencial / enviar a aprobación.
 */
function requisicionCompletaParaControl(Departamento $depto): Requisicion
{
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $depto->id]);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 5]);
    $precio = RequisicionCotizacionPrecio::factory()->create(['requisicion_detalle_id' => $detalle->id]);
    RequisicionCotizacionPrecio::factory()->count(2)->create(['requisicion_detalle_id' => $detalle->id]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'proveedor_id' => $precio->proveedor_id,
        'cantidad' => 5,
    ]);
    $req->ocs()->create(['proveedor_id' => $precio->proveedor_id, 'numero_oc' => 1]);

    return $req;
}

beforeEach(function () {
    foreach (['costos.requisiciones.cotizar', 'costos.requisiciones.control', 'costos.requisiciones.crear'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->depto = Departamento::factory()->create();

    $this->control = User::factory()->create();
    $this->control->givePermissionTo('costos.requisiciones.control');

    $this->compras = User::factory()->create();
    $this->compras->givePermissionTo(['costos.requisiciones.cotizar', 'costos.requisiciones.crear']);
});

test('marcar el punto de control requiere permiso propio', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/punto-control")
        ->assertForbidden();

    expect($req->fresh()->control_verificado)->toBeFalse();
});

test('con permiso marca el punto de control y registra quién y cuándo', function () {
    $req = requisicionCompletaParaControl($this->depto);

    $this->actingAs($this->control)
        ->post("/admin/costos/requisiciones/{$req->id}/punto-control")
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $req->refresh();
    expect($req->control_verificado)->toBeTrue()
        ->and($req->control_por)->toBe($this->control->id)
        ->and($req->control_at)->not->toBeNull();
});

test('no marca el punto de control si la cotización está incompleta (mismas validaciones que enviar a aprobación)', function () {
    // Cotizada pero sin cotizaciones/selecciones/OC: no debe pasar el control.
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id]);

    $this->actingAs($this->control)
        ->post("/admin/costos/requisiciones/{$req->id}/punto-control")
        ->assertSessionHasErrors(['cotizaciones']);

    expect($req->fresh()->control_verificado)->toBeFalse();
});

test('se puede quitar el punto de control', function () {
    $req = Requisicion::factory()->cotizada()->create([
        'departamento_id' => $this->depto->id,
        'control_verificado' => true,
        'control_por' => $this->control->id,
    ]);

    $this->actingAs($this->control)
        ->delete("/admin/costos/requisiciones/{$req->id}/punto-control")
        ->assertRedirect();

    $req->refresh();
    expect($req->control_verificado)->toBeFalse()
        ->and($req->control_por)->toBeNull();
});

test('el punto de control solo aplica a requisiciones cotizadas', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);

    $this->actingAs($this->control)
        ->post("/admin/costos/requisiciones/{$req->id}/punto-control")
        ->assertSessionHasErrors(['control']);

    expect($req->fresh()->control_verificado)->toBeFalse();
});

test('no se puede enviar a aprobación sin el punto de control', function () {
    $req = Requisicion::factory()->cotizada()->create([
        'departamento_id' => $this->depto->id,
        'control_verificado' => false,
    ]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/enviar-aprobacion")
        ->assertSessionHasErrors(['control']);

    expect($req->fresh()->estatus->value)->toBe('cotizada');
});

test('editar la requisición invalida un punto de control previo', function () {
    $rubro = ObraRubro::factory()->create();
    $uso = UsoCfdi::factory()->create(['activo' => true]);
    $req = Requisicion::factory()->create([
        'departamento_id' => $this->depto->id,
        'estatus' => 'borrador',
        'presupuesto_id' => null,
        'control_verificado' => true,
        'control_por' => $this->control->id,
    ]);

    $this->actingAs($this->compras)
        ->put("/admin/costos/requisiciones/{$req->id}", [
            'departamento_id' => $this->depto->id,
            'detalles' => [
                ['descripcion' => 'Tornillos', 'unidad' => 'pza', 'cantidad' => 10, 'obra_rubro_id' => $rubro->id, 'uso_cfdi_id' => $uso->id],
            ],
            '_version' => $req->updated_at->toIso8601String(),
        ])
        ->assertRedirect();

    $req->refresh();
    expect($req->control_verificado)->toBeFalse()
        ->and($req->control_por)->toBeNull();
});
