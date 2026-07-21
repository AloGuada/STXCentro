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
 * Requisición en la etapa interna y COMPLETA (3 proveedores, selección al 100%
 * con precio y una OC), lista para la aprobación interna del gerente.
 */
function requisicionCompletaParaControl(Departamento $depto, string $estatus = 'pendiente_aprobacion_interno'): Requisicion
{
    $req = Requisicion::factory()->create(['departamento_id' => $depto->id, 'estatus' => $estatus]);
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

test('la aprobación interna requiere permiso propio', function () {
    $req = Requisicion::factory()->pendienteAprobacionInterna()->create(['departamento_id' => $this->depto->id]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/aprobar-interno")
        ->assertForbidden();

    expect($req->fresh()->estatus->value)->toBe('pendiente_aprobacion_interno');
});

test('con permiso aprueba internamente, transiciona a aprobada_interna y registra quién y cuándo', function () {
    $req = requisicionCompletaParaControl($this->depto);

    $this->actingAs($this->control)
        ->post("/admin/costos/requisiciones/{$req->id}/aprobar-interno")
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $req->refresh();
    expect($req->estatus->value)->toBe('aprobada_interna')
        ->and($req->control_por)->toBe($this->control->id)
        ->and($req->control_at)->not->toBeNull();
});

test('no aprueba internamente si la cotización está incompleta', function () {
    // Interna pero sin cotizaciones/selecciones/OC: no debe pasar el control.
    $req = Requisicion::factory()->pendienteAprobacionInterna()->create(['departamento_id' => $this->depto->id]);

    $this->actingAs($this->control)
        ->post("/admin/costos/requisiciones/{$req->id}/aprobar-interno")
        ->assertSessionHasErrors(['cotizaciones']);

    expect($req->fresh()->estatus->value)->toBe('pendiente_aprobacion_interno');
});

test('rechazar la aprobación interna regresa la requisición a borrador', function () {
    $req = requisicionCompletaParaControl($this->depto);
    $req->update(['control_por' => $this->control->id, 'control_at' => now()]);

    $this->actingAs($this->control)
        ->post("/admin/costos/requisiciones/{$req->id}/rechazar-interno")
        ->assertRedirect();

    $req->refresh();
    expect($req->estatus->value)->toBe('borrador')
        ->and($req->control_por)->toBeNull();
});

test('la aprobación interna solo aplica a requisiciones pendientes de aprobación interna', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);

    $this->actingAs($this->control)
        ->post("/admin/costos/requisiciones/{$req->id}/aprobar-interno")
        ->assertSessionHasErrors(['control']);

    expect($req->fresh()->estatus->value)->toBe('borrador');
});

test('no se puede mandar a aprobación sin la aprobación interna', function () {
    $req = requisicionCompletaParaControl($this->depto);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/iniciar-aprobacion")
        ->assertSessionHasErrors(['estatus']);

    expect($req->fresh()->estatus->value)->toBe('pendiente_aprobacion_interno')
        ->and($req->cadenaAprobacion()->exists())->toBeFalse();
});

test('mandar a aprobación desde aprobada_interna transiciona a pendiente de aprobación', function () {
    $req = requisicionCompletaParaControl($this->depto, 'aprobada_interna');

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/iniciar-aprobacion")
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($req->fresh()->estatus->value)->toBe('pendiente_aprobacion');
});

test('editar la requisición invalida una aprobación interna previa', function () {
    $rubro = ObraRubro::factory()->create();
    $uso = UsoCfdi::factory()->create(['activo' => true]);
    $req = Requisicion::factory()->create([
        'departamento_id' => $this->depto->id,
        'estatus' => 'borrador',
        'presupuesto_id' => null,
        'control_por' => $this->control->id,
        'control_at' => now(),
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
    expect($req->control_por)->toBeNull()
        ->and($req->control_at)->toBeNull();
});
