<?php

use App\Enums\ProveedorEstatus;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.requisiciones.aprobar', 'costos.requisiciones.ver'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->aprobador = User::factory()->create();
    $this->aprobador->givePermissionTo(['costos.requisiciones.aprobar', 'costos.requisiciones.ver']);

    $this->depto = Departamento::factory()->create();

    // Firmar-final exige el gate 'aprobador-costos': estar asignado como
    // aprobador en costos_aprobacion_departamento.
    \App\Models\Costos\AprobacionDepartamento::factory()->create(['aprobador_id' => $this->aprobador->id]);
});

/**
 * Crea una requisición en pendiente_aprobacion con una partida, dos cotizaciones
 * (proveedor pendiente ganador + proveedor activo alterno), una selección al
 * proveedor pendiente y una aprobación de nivel 1 en turno del aprobador.
 *
 * @return array{0: Requisicion, 1: RequisicionDetalle, 2: Proveedor, 3: Proveedor, 4: RequisicionCotizacionPrecio, 5: RequisicionCotizacionPrecio, 6: RequisicionSeleccion}
 */
function setupRequisicionParaFirma(Departamento $depto, User $aprobador): array
{
    $rubro = ObraRubro::factory()->create();
    $req = Requisicion::factory()->pendienteAprobacion()->create(['departamento_id' => $depto->id]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => $rubro->id,
        'cantidad' => 10,
        'descripcion' => 'Tornillos',
        'unidad' => 'pza',
    ]);

    $provPendiente = Proveedor::factory()->pendienteValidacion()->create();
    $provAlterno = Proveedor::factory()->create(); // activo por defecto

    $precioPendiente = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $provPendiente->id,
        'precio_unitario' => 20.00,
    ]);
    $precioAlterno = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $provAlterno->id,
        'precio_unitario' => 25.00,
    ]);

    $seleccion = RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precioPendiente->id,
        'numero_oc' => 1,
        'proveedor_id' => $provPendiente->id,
        'cantidad' => 10,
    ]);

    $req->aprobaciones()->create([
        'nivel' => 1,
        'aprobador_id' => $aprobador->id,
        'estatus' => 'pendiente',
    ]);

    return [$req, $detalle, $provPendiente, $provAlterno, $precioPendiente, $precioAlterno, $seleccion];
}

test('activar al proveedor en el último nivel lo activa y aprueba la requisición', function () {
    [$req, , $provPendiente] = setupRequisicionParaFirma($this->depto, $this->aprobador);

    $this->actingAs($this->aprobador)
        ->post("/admin/costos/requisiciones/{$req->id}/firmar-final", [
            'observaciones' => 'Documentación correcta',
            'validaciones' => [
                ['proveedor_id' => $provPendiente->id, 'accion' => 'activar'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $provPendiente->refresh();
    $req->refresh();
    expect($provPendiente->estatus)->toBe(ProveedorEstatus::Activo);
    expect($provPendiente->activo)->toBeTrue();
    expect($req->estatus->value)->toBe('aprobada');
});

test('rechazar al proveedor con reemplazo reasigna la partida y aprueba la requisición', function () {
    [$req, $detalle, $provPendiente, $provAlterno, , $precioAlterno, $seleccion] = setupRequisicionParaFirma($this->depto, $this->aprobador);

    $this->actingAs($this->aprobador)
        ->post("/admin/costos/requisiciones/{$req->id}/firmar-final", [
            'observaciones' => 'Documentación incompleta, se reasigna',
            'validaciones' => [
                [
                    'proveedor_id' => $provPendiente->id,
                    'accion' => 'rechazar',
                    'reemplazos' => [
                        [
                            'requisicion_detalle_id' => $detalle->id,
                            'nuevo_proveedor_id' => $provAlterno->id,
                            'cotizacion_precio_id' => $precioAlterno->id,
                        ],
                    ],
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $provPendiente->refresh();
    $seleccion->refresh();
    $req->refresh();
    expect($provPendiente->estatus)->toBe(ProveedorEstatus::Rechazado);
    expect($seleccion->proveedor_id)->toBe($provAlterno->id);
    expect($seleccion->cotizacion_precio_id)->toBe($precioAlterno->id);
    expect($req->estatus->value)->toBe('aprobada');
});

test('rechazar al proveedor sin reemplazo rechaza la requisición', function () {
    [$req, , $provPendiente] = setupRequisicionParaFirma($this->depto, $this->aprobador);

    $this->actingAs($this->aprobador)
        ->post("/admin/costos/requisiciones/{$req->id}/firmar-final", [
            'observaciones' => 'Documentación inválida, sin alternativa',
            'validaciones' => [
                ['proveedor_id' => $provPendiente->id, 'accion' => 'rechazar', 'reemplazos' => []],
            ],
        ])
        ->assertRedirect();

    $provPendiente->refresh();
    $req->refresh();
    expect($provPendiente->estatus)->toBe(ProveedorEstatus::Rechazado);
    expect($req->estatus->value)->toBe('rechazada');
});

test('la lista de cotización incluye proveedores pendientes y excluye rechazados', function () {
    $pendiente = Proveedor::factory()->pendienteValidacion()->create(['razon_social' => 'Pendiente SA']);
    $activo = Proveedor::factory()->create(['razon_social' => 'Activo SA']);
    $rechazado = Proveedor::factory()->rechazado()->create(['razon_social' => 'Rechazado SA']);

    $req = Requisicion::factory()->pendienteAprobacion()->create([
        'departamento_id' => $this->depto->id,
        'solicitante_id' => $this->aprobador->id,
    ]);

    $this->actingAs($this->aprobador)
        ->get("/admin/costos/requisiciones/{$req->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/costos/requisiciones/show')
            ->where('proveedores', function ($proveedores) use ($pendiente, $activo, $rechazado) {
                $ids = collect($proveedores)->pluck('id')->all();

                return in_array($pendiente->id, $ids, true)
                    && in_array($activo->id, $ids, true)
                    && ! in_array($rechazado->id, $ids, true);
            })
        );
});
