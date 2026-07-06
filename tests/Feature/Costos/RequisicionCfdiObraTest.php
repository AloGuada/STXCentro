<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Costos\UsoCfdi;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.requisiciones.crear', 'costos.requisiciones.liberar'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.requisiciones.crear', 'costos.requisiciones.liberar']);

    $this->depto = Departamento::factory()->create();
    $this->obra = Obra::factory()->create();
    $this->presupuesto = Presupuesto::factory()->paraObra($this->obra)->create();
    $this->uso = UsoCfdi::factory()->create(['clave' => 'G01']);
});

test('crear requisición exige uso de CFDI por partida; la obra es opcional (multiobra)', function () {
    $rubro = ObraRubro::factory()->create(['obra_id' => $this->obra->id]);

    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            // sin obra_id → permitido (multiobra)
            'detalles' => [
                ['descripcion' => 'Acero', 'unidad' => 'kg', 'cantidad' => 10, 'obra_rubro_id' => $rubro->id],
            ],
        ])
        ->assertSessionHasErrors(['detalles.0.uso_cfdi_id'])
        ->assertSessionDoesntHaveErrors(['obra_id']);
});

test('rechaza un uso de CFDI inactivo', function () {
    $rubro = ObraRubro::factory()->create(['obra_id' => $this->obra->id]);
    $inactivo = UsoCfdi::factory()->inactivo()->create();

    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'obra_id' => $this->obra->id,
            'detalles' => [
                ['descripcion' => 'Acero', 'unidad' => 'kg', 'cantidad' => 10, 'obra_rubro_id' => $rubro->id, 'uso_cfdi_id' => $inactivo->id],
            ],
        ])
        ->assertSessionHasErrors(['detalles.0.uso_cfdi_id']);
});

test('rechaza una partida cuyo rubro no pertenece al presupuesto elegido', function () {
    $rubroOtroPresupuesto = ObraRubro::factory()->create(); // presupuesto distinto

    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'presupuesto_id' => $this->presupuesto->id,
            'detalles' => [
                ['descripcion' => 'Acero', 'unidad' => 'kg', 'cantidad' => 10, 'obra_rubro_id' => $rubroOtroPresupuesto->id, 'uso_cfdi_id' => $this->uso->id],
            ],
        ])
        ->assertSessionHasErrors(['detalles.0.obra_rubro_id']);
});

test('crea la requisición con presupuesto, código de producto y uso de CFDI por partida', function () {
    $rubro = ObraRubro::factory()->create(['presupuesto_id' => $this->presupuesto->id]);

    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'presupuesto_id' => $this->presupuesto->id,
            'detalles' => [
                ['descripcion' => 'Acero', 'codigo_producto' => 'ACE-001', 'unidad' => 'kg', 'cantidad' => 10, 'obra_rubro_id' => $rubro->id, 'uso_cfdi_id' => $this->uso->id],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $detalle = RequisicionDetalle::first();
    expect($detalle->codigo_producto)->toBe('ACE-001');
    expect($detalle->uso_cfdi_id)->toBe($this->uso->id);
    expect(Requisicion::first()->presupuesto_id)->toBe($this->presupuesto->id);
});

test('liberar bloquea si una partida no tiene uso de CFDI', function () {
    $rubro = ObraRubro::factory()->create(['obra_id' => $this->obra->id]);
    $req = Requisicion::factory()->aprobada()->create([
        'departamento_id' => $this->depto->id,
        'obra_id' => $this->obra->id,
    ]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => $rubro->id,
        'uso_cfdi_id' => null, // sin uso de CFDI
        'cantidad' => 5,
    ]);
    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'numero_oc' => 1,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 5,
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar", [
            'ocs' => [
                ['proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => 'contado', 'moneda' => 'mxn'],
            ],
        ])
        ->assertSessionHasErrors(['detalles']);

    expect(OrdenCompra::count())->toBe(0);
});

test('al liberar, el uso de CFDI y el código de producto se heredan a la OC', function () {
    $rubro = ObraRubro::factory()->create(['obra_id' => $this->obra->id]);
    $req = Requisicion::factory()->aprobada()->create([
        'departamento_id' => $this->depto->id,
        'obra_id' => $this->obra->id,
    ]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => $rubro->id,
        'uso_cfdi_id' => $this->uso->id,
        'codigo_producto' => 'ACE-001',
        'cantidad' => 5,
    ]);
    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
        'precio_unitario' => 100,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'numero_oc' => 1,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 5,
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar", [
            'ocs' => [
                ['proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => 'contado', 'moneda' => 'mxn'],
            ],
        ])
        ->assertRedirect();

    $ocDetalle = OrdenCompra::first()->detalles()->first();
    expect($ocDetalle->uso_cfdi_id)->toBe($this->uso->id);
    expect($ocDetalle->codigo_producto)->toBe('ACE-001');
});
