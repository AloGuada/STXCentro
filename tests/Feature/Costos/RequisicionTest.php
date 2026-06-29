<?php

use App\Models\Costos\Aprobacion;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Permiso;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'costos.requisiciones.ver',
        'costos.requisiciones.crear',
        'costos.requisiciones.cotizar',
        'costos.requisiciones.aprobar',
        'costos.requisiciones.cancelar',
    ] as $permName) {
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.requisiciones.ver', 'costos.requisiciones.crear']);

    $this->compras = User::factory()->create();
    $this->compras->givePermissionTo([
        'costos.requisiciones.ver',
        'costos.requisiciones.crear',
        'costos.requisiciones.cotizar',
        'costos.requisiciones.cancelar',
    ]);

    $this->depto = Departamento::factory()->create();
});

test('cualquier usuario con permiso crear puede crear una requisicion en borrador', function () {
    $obra = \App\Models\Obra::factory()->create();
    $rubroA = ObraRubro::factory()->create(['obra_id' => $obra->id]);
    $rubroB = ObraRubro::factory()->create(['obra_id' => $obra->id]);
    $uso = \App\Models\Costos\UsoCfdi::factory()->create();

    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'obra_id' => $obra->id,
            'detalles' => [
                ['descripcion' => 'Tornillos 1/4"', 'unidad' => 'pza', 'cantidad' => 100, 'obra_rubro_id' => $rubroA->id, 'uso_cfdi_id' => $uso->id],
                ['descripcion' => 'Cable AWG 12', 'unidad' => 'm', 'cantidad' => 50, 'obra_rubro_id' => $rubroB->id, 'uso_cfdi_id' => $uso->id],
            ],
        ])
        ->assertRedirect();

    $req = Requisicion::first();
    expect($req)->not->toBeNull();
    expect($req->estatus->value)->toBe('borrador');
    expect($req->folio)->toStartWith('REQ-');
    expect($req->obra_id)->toBe($obra->id);
    expect($req->detalles()->count())->toBe(2);
    expect($req->detalles()->first()->obra_rubro_id)->toBe($rubroA->id);
});

test('crear requisicion liga la partida a un producto existente del catalogo', function () {
    $obra = \App\Models\Obra::factory()->create();
    $rubro = ObraRubro::factory()->create(['obra_id' => $obra->id]);
    $uso = \App\Models\Costos\UsoCfdi::factory()->create();
    $producto = \App\Models\Costos\Producto::factory()->create(['descripcion' => 'Cemento gris', 'unidad' => 'saco']);

    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'obra_id' => $obra->id,
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Cemento gris', 'unidad' => 'saco', 'cantidad' => 10, 'obra_rubro_id' => $rubro->id, 'uso_cfdi_id' => $uso->id],
            ],
        ])
        ->assertRedirect();

    $detalle = Requisicion::first()->detalles()->first();
    expect($detalle->producto_id)->toBe($producto->id)
        ->and($detalle->descripcion)->toBe('Cemento gris')
        ->and($detalle->unidad)->toBe('saco');
});

test('crear requisicion con producto nuevo lo da de alta en el catalogo', function () {
    $obra = \App\Models\Obra::factory()->create();
    $rubro = ObraRubro::factory()->create(['obra_id' => $obra->id]);
    $uso = \App\Models\Costos\UsoCfdi::factory()->create();

    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'obra_id' => $obra->id,
            'detalles' => [
                ['producto_id' => null, 'descripcion' => 'Producto nuevo X', 'unidad' => 'pza', 'cantidad' => 3, 'obra_rubro_id' => $rubro->id, 'uso_cfdi_id' => $uso->id],
            ],
        ])
        ->assertRedirect();

    $producto = \App\Models\Costos\Producto::where('descripcion', 'Producto nuevo X')->first();
    expect($producto)->not->toBeNull()
        ->and($producto->creado_por)->toBe($this->user->id);

    $detalle = Requisicion::first()->detalles()->first();
    expect($detalle->producto_id)->toBe($producto->id);
});

test('crear requisicion sin rubro por partida falla', function () {
    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'detalles' => [
                ['descripcion' => 'Algo', 'unidad' => 'pza', 'cantidad' => 1],
            ],
        ])
        ->assertSessionHasErrors(['detalles.0.obra_rubro_id']);
});

test('compras captura precio cotizado y la requisicion pasa a cotizada', function () {
    $req = Requisicion::factory()->create([
        'departamento_id' => $this->depto->id,
        'estatus' => 'borrador',
    ]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 10,
    ]);
    $proveedor = Proveedor::factory()->create();

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/cotizaciones', [
            'requisicion_detalle_id' => $detalle->id,
            'proveedor_id' => $proveedor->id,
            'precio_unitario' => 250.50,
            'moneda' => 'mxn',
        ])
        ->assertRedirect();

    expect($req->fresh()->estatus->value)->toBe('cotizada');
    expect(RequisicionCotizacionPrecio::count())->toBe(1);
});

test('seleccion no puede exceder cantidad de partida', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 10,
    ]);
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
    ]);

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/selecciones', [
            'cotizacion_precio_id' => $precio->id,
            'cantidad' => 15, // > 10
        ])
        ->assertSessionHasErrors(['cantidad']);
});

test('seleccion del mismo proveedor se consolida sumando cantidades', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 20,
    ]);
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
    ]);

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/selecciones', [
            'cotizacion_precio_id' => $precio->id,
            'cantidad' => 5,
        ])
        ->assertRedirect();

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/selecciones', [
            'cotizacion_precio_id' => $precio->id,
            'cantidad' => 7,
        ])
        ->assertRedirect();

    // Solo una fila, con cantidad sumada
    expect(RequisicionSeleccion::where('cotizacion_precio_id', $precio->id)->count())->toBe(1);
    expect((float) RequisicionSeleccion::where('cotizacion_precio_id', $precio->id)->first()->cantidad)
        ->toBe(12.0);
});

test('consolidacion respeta el limite de cantidad solicitada', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 10,
    ]);
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
    ]);

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/selecciones', [
            'cotizacion_precio_id' => $precio->id,
            'cantidad' => 8,
        ])
        ->assertRedirect();

    // Intento sumar 5 (8+5 = 13 > 10) — debe fallar y no incrementar
    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/selecciones', [
            'cotizacion_precio_id' => $precio->id,
            'cantidad' => 5,
        ])
        ->assertSessionHasErrors(['cantidad']);

    expect((float) RequisicionSeleccion::where('cotizacion_precio_id', $precio->id)->first()->cantidad)
        ->toBe(8.0);
});

test('seleccion permite split entre dos proveedores', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 10,
    ]);
    $precioA = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
    ]);
    $precioB = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
    ]);

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/selecciones', [
            'cotizacion_precio_id' => $precioA->id,
            'cantidad' => 6,
        ])
        ->assertRedirect();

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/selecciones', [
            'cotizacion_precio_id' => $precioB->id,
            'cantidad' => 4,
        ])
        ->assertRedirect();

    expect(RequisicionSeleccion::count())->toBe(2);
});

test('enviar a aprobacion genera cadena por niveles del departamento', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 5,
    ]);
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'proveedor_id' => $precio->proveedor_id,
        'cantidad' => 5,
    ]);

    // Configurar 2 niveles de aprobacion para tipo_aprobacion='requisicion'
    $permiso1 = Permiso::create([
        'descripcion' => 'Costos',
        'nivel' => 1,
        'tipo_aprobacion' => 'requisicion',
    ]);
    $permiso2 = Permiso::create([
        'descripcion' => 'Director',
        'nivel' => 2,
        'tipo_aprobacion' => 'requisicion',
    ]);
    $aprobador1 = User::factory()->create();
    $aprobador2 = User::factory()->create();
    AprobacionDepartamento::create([
        'departamento_id' => $this->depto->id,
        'permiso_id' => $permiso1->id,
        'aprobador_id' => $aprobador1->id,
    ]);
    AprobacionDepartamento::create([
        'departamento_id' => $this->depto->id,
        'permiso_id' => $permiso2->id,
        'aprobador_id' => $aprobador2->id,
    ]);

    // Permiso de SolicitudPago no debe entrar en la cadena de requisicion
    $permisoSP = Permiso::create([
        'descripcion' => 'SP',
        'nivel' => 1,
        'tipo_aprobacion' => 'solicitud_pago',
    ]);
    AprobacionDepartamento::create([
        'departamento_id' => $this->depto->id,
        'permiso_id' => $permisoSP->id,
        'aprobador_id' => User::factory()->create()->id,
    ]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/enviar-aprobacion")
        ->assertRedirect();

    $req->refresh();
    expect($req->estatus->value)->toBe('pendiente_aprobacion');
    expect($req->aprobaciones()->count())->toBe(2);
    expect(Aprobacion::where('aprobable_type', Requisicion::class)
        ->where('aprobable_id', $req->id)
        ->where('aprobador_id', $aprobador1->id)
        ->count())->toBe(1);
});

test('no puede enviar a aprobacion sin selecciones completas', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id]);
    RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 5,
    ]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/enviar-aprobacion")
        ->assertSessionHasErrors(['selecciones']);
});

test('no puede enviar a aprobacion si una partida no tiene rubro asignado', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => null,
        'cantidad' => 5,
    ]);
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'proveedor_id' => $precio->proveedor_id,
        'cantidad' => 5,
    ]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/enviar-aprobacion")
        ->assertSessionHasErrors(['detalles']);
});

test('numero_oc consolida por (partida, precio, numero_oc) y crea filas separadas para distintos OC#', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 20,
    ]);
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
    ]);

    // OC#1 con 5 unidades
    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/selecciones', [
            'cotizacion_precio_id' => $precio->id,
            'cantidad' => 5,
            'numero_oc' => 1,
        ])
        ->assertRedirect();

    // OC#1 con 3 más → debe consolidar en la misma fila
    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/selecciones', [
            'cotizacion_precio_id' => $precio->id,
            'cantidad' => 3,
            'numero_oc' => 1,
        ])
        ->assertRedirect();

    // OC#2 con 4 → fila distinta
    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/selecciones', [
            'cotizacion_precio_id' => $precio->id,
            'cantidad' => 4,
            'numero_oc' => 2,
        ])
        ->assertRedirect();

    expect(RequisicionSeleccion::where('cotizacion_precio_id', $precio->id)->count())->toBe(2);

    $oc1 = RequisicionSeleccion::where('cotizacion_precio_id', $precio->id)
        ->where('numero_oc', 1)
        ->first();
    expect((float) $oc1->cantidad)->toBe(8.0);

    $oc2 = RequisicionSeleccion::where('cotizacion_precio_id', $precio->id)
        ->where('numero_oc', 2)
        ->first();
    expect((float) $oc2->cantidad)->toBe(4.0);
});

test('rechazo permite volver a borrador para re-cotizar', function () {
    $req = Requisicion::factory()->rechazada()->create(['departamento_id' => $this->depto->id]);

    $req->transitionTo(\App\Enums\Costos\RequisicionEstatus::Borrador);

    expect($req->fresh()->estatus->value)->toBe('borrador');
});

test('Requisicion implementa Aprobable y onAprobacionCompleta transiciona a aprobada', function () {
    $req = Requisicion::factory()->create([
        'estatus' => 'pendiente_aprobacion',
        'departamento_id' => $this->depto->id,
    ]);

    expect($req->tipoAprobacion())->toBe('requisicion');

    $req->onAprobacionCompleta();

    expect($req->fresh()->estatus->value)->toBe('aprobada');
});

test('onAprobacionRechazada guarda motivo y transiciona a rechazada', function () {
    $req = Requisicion::factory()->create([
        'estatus' => 'pendiente_aprobacion',
        'departamento_id' => $this->depto->id,
    ]);

    $req->onAprobacionRechazada('precio fuera de mercado');

    $req->refresh();
    expect($req->estatus->value)->toBe('rechazada');
    expect($req->motivo_rechazo)->toBe('precio fuera de mercado');
});
