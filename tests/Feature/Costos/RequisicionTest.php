<?php

use App\Models\Costos\Aprobacion;
use App\Models\Costos\AprobacionDepartamento;
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
    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'concepto' => 'Materiales para obra X',
            'detalles' => [
                ['descripcion' => 'Tornillos 1/4"', 'unidad' => 'pza', 'cantidad' => 100],
                ['descripcion' => 'Cable AWG 12', 'unidad' => 'm', 'cantidad' => 50],
            ],
        ])
        ->assertRedirect();

    $req = Requisicion::first();
    expect($req)->not->toBeNull();
    expect($req->estatus->value)->toBe('borrador');
    expect($req->folio)->toStartWith('REQ-');
    expect($req->detalles()->count())->toBe(2);
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
