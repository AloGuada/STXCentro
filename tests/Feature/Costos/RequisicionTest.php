<?php

use App\Models\Costos\Aprobacion;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Permiso;
use App\Models\Costos\Presupuesto;
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

test('el selector de presupuesto usa la OP y descripción internas y cae a cobranza si faltan', function () {
    // Con datos internos → se muestran esos.
    $obraInterna = \App\Models\Obra::factory()->create(['no' => 'OP-COB-1', 'descripcion' => 'Desc Cobranza 1']);
    Presupuesto::factory()->paraObra($obraInterna)->create([
        'op_interno' => 'OP-INT-1',
        'nombre_interno' => 'Nombre Interno 1',
    ]);

    // Sin datos internos → cae a la OP/descripción de cobranza (obra).
    $obraCobranza = \App\Models\Obra::factory()->create(['no' => 'OP-COB-2', 'descripcion' => 'Desc Cobranza 2']);
    Presupuesto::factory()->paraObra($obraCobranza)->create([
        'op_interno' => null,
        'nombre_interno' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.costos.requisiciones.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('presupuestos', fn ($presupuestos) => collect($presupuestos)->pluck('label')->all() === [
            'OP-COB-2 - Desc Cobranza 2',
            'OP-INT-1 - Nombre Interno 1',
        ])
    );
});

test('al crear una requisición se adjuntan los PDF de cotización', function () {
    \Illuminate\Support\Facades\Storage::fake('public');
    $obra = \App\Models\Obra::factory()->create();
    $rubro = ObraRubro::factory()->create(['obra_id' => $obra->id]);
    $uso = \App\Models\Costos\UsoCfdi::factory()->create();

    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'obra_id' => $obra->id,
            'detalles' => [
                ['descripcion' => 'Tornillos', 'unidad' => 'pza', 'cantidad' => 10, 'obra_rubro_id' => $rubro->id, 'uso_cfdi_id' => $uso->id],
            ],
            'documentos' => [
                \Illuminate\Http\UploadedFile::fake()->create('cotiz-a.pdf', 100, 'application/pdf'),
                \Illuminate\Http\UploadedFile::fake()->create('cotiz-b.pdf', 100, 'application/pdf'),
            ],
        ])
        ->assertRedirect();

    $req = Requisicion::first();
    expect($req->media()->count())->toBe(2);
    \Illuminate\Support\Facades\Storage::disk('public')->assertExists($req->media()->first()->path);
});

test('rechaza documentos que no son PDF al crear', function () {
    \Illuminate\Support\Facades\Storage::fake('public');
    $obra = \App\Models\Obra::factory()->create();
    $rubro = ObraRubro::factory()->create(['obra_id' => $obra->id]);
    $uso = \App\Models\Costos\UsoCfdi::factory()->create();

    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'obra_id' => $obra->id,
            'detalles' => [
                ['descripcion' => 'Tornillos', 'unidad' => 'pza', 'cantidad' => 10, 'obra_rubro_id' => $rubro->id, 'uso_cfdi_id' => $uso->id],
            ],
            'documentos' => [\Illuminate\Http\UploadedFile::fake()->image('foto.jpg')],
        ])
        ->assertSessionHasErrors('documentos.0');

    expect(Requisicion::count())->toBe(0);
});

test('cualquier usuario con permiso crear puede crear una requisicion en borrador', function () {
    $presupuesto = Presupuesto::factory()->paraObra()->create();
    $rubroA = ObraRubro::factory()->create(['presupuesto_id' => $presupuesto->id]);
    $rubroB = ObraRubro::factory()->create(['presupuesto_id' => $presupuesto->id]);
    $uso = \App\Models\Costos\UsoCfdi::factory()->create();

    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'presupuesto_id' => $presupuesto->id,
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
    expect($req->presupuesto_id)->toBe($presupuesto->id);
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

test('crea requisicion multipresupuesto (sin cabecera) con partidas de distintos presupuestos', function () {
    $rubroA = ObraRubro::factory()->create(['presupuesto_id' => Presupuesto::factory()->paraObra()->create()->id]);
    $rubroB = ObraRubro::factory()->create(['presupuesto_id' => Presupuesto::factory()->paraObra()->create()->id]);
    $uso = \App\Models\Costos\UsoCfdi::factory()->create();

    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            // sin presupuesto_id → multipresupuesto
            'detalles' => [
                ['descripcion' => 'A', 'unidad' => 'pza', 'cantidad' => 1, 'obra_rubro_id' => $rubroA->id, 'uso_cfdi_id' => $uso->id],
                ['descripcion' => 'B', 'unidad' => 'pza', 'cantidad' => 2, 'obra_rubro_id' => $rubroB->id, 'uso_cfdi_id' => $uso->id],
            ],
        ])
        ->assertRedirect();

    $req = Requisicion::first();
    expect($req)->not->toBeNull()
        ->and($req->presupuesto_id)->toBeNull()
        ->and($req->detalles()->count())->toBe(2);
});

test('crea una requisicion contra un presupuesto de proyecto', function () {
    $presupuesto = Presupuesto::factory()->paraProyecto()->create();
    $rubro = ObraRubro::factory()->create(['presupuesto_id' => $presupuesto->id]);
    $uso = \App\Models\Costos\UsoCfdi::factory()->create();

    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'presupuesto_id' => $presupuesto->id,
            'detalles' => [
                ['descripcion' => 'Servicio', 'unidad' => 'srv', 'cantidad' => 1, 'obra_rubro_id' => $rubro->id, 'uso_cfdi_id' => $uso->id],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Requisicion::first()->presupuesto_id)->toBe($presupuesto->id);
});

test('requisicion de un solo presupuesto sigue validando la pertenencia del centro de costos', function () {
    $presupuestoA = Presupuesto::factory()->paraObra()->create();
    $presupuestoB = Presupuesto::factory()->paraObra()->create();
    $rubroB = ObraRubro::factory()->create(['presupuesto_id' => $presupuestoB->id]); // de OTRO presupuesto
    $uso = \App\Models\Costos\UsoCfdi::factory()->create();

    $this->actingAs($this->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => $this->depto->id,
            'presupuesto_id' => $presupuestoA->id, // A pero rubro de B → debe fallar
            'detalles' => [
                ['descripcion' => 'X', 'unidad' => 'pza', 'cantidad' => 1, 'obra_rubro_id' => $rubroB->id, 'uso_cfdi_id' => $uso->id],
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
    $opcion = App\Models\Costos\RequisicionCotizacionOpcion::create([
        'requisicion_id' => $req->id,
        'proveedor_id' => $proveedor->id,
        'orden' => 1,
    ]);

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/cotizaciones', [
            'requisicion_detalle_id' => $detalle->id,
            'opcion_id' => $opcion->id,
            'precio_unitario' => 250.50,
            'moneda' => 'mxn',
        ])
        ->assertRedirect();

    expect($req->fresh()->estatus->value)->toBe('cotizada');
    expect(RequisicionCotizacionPrecio::count())->toBe(1);
});

test('seleccion no puede exceder cantidad de partida', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id, 'control_verificado' => true]);
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
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id, 'control_verificado' => true]);
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
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id, 'control_verificado' => true]);
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
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id, 'control_verificado' => true]);
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
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id, 'control_verificado' => true]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 5,
    ]);
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
    ]);
    // Regla de compras: mínimo 3 empresas cotizantes por partida.
    RequisicionCotizacionPrecio::factory()->count(2)->create([
        'requisicion_detalle_id' => $detalle->id,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'proveedor_id' => $precio->proveedor_id,
        'cantidad' => 5,
    ]);

    $req->ocs()->create(['proveedor_id' => $precio->proveedor_id, 'numero_oc' => 1]);

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

test('el show expone el último precio de cada proveedor para el insumo (preciosPrevios)', function () {
    $producto = \App\Models\Costos\Producto::factory()->create();
    $proveedor = Proveedor::factory()->create();

    // Requisición histórica (otra) con una cotización del proveedor para el insumo.
    $reqVieja = Requisicion::factory()->create(['departamento_id' => $this->depto->id]);
    $detalleViejo = RequisicionDetalle::factory()->create([
        'requisicion_id' => $reqVieja->id,
        'producto_id' => $producto->id,
    ]);
    RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalleViejo->id,
        'proveedor_id' => $proveedor->id,
        'precio_unitario' => 123.45,
    ]);

    // Requisición actual: mismo insumo y una opción para ese proveedor.
    $req = Requisicion::factory()->cotizada()->create([
        'departamento_id' => $this->depto->id,
        'solicitante_id' => $this->compras->id,
    ]);
    RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'producto_id' => $producto->id,
    ]);
    $req->cotizacionOpciones()->create(['proveedor_id' => $proveedor->id, 'orden' => 1]);

    $this->actingAs($this->compras)
        ->get("/admin/costos/requisiciones/{$req->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where(
                "preciosPrevios.{$producto->id}|{$proveedor->id}",
                fn ($v) => abs((float) $v - 123.45) < 0.01,
            )
        );
});

test('no puede enviar a aprobacion sin selecciones completas', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id, 'control_verificado' => true]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 5,
    ]);
    // 3 empresas cotizantes (pasa la regla) pero sin ninguna selección.
    RequisicionCotizacionPrecio::factory()->count(3)->create([
        'requisicion_detalle_id' => $detalle->id,
    ]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/enviar-aprobacion")
        ->assertSessionHasErrors(['selecciones']);
});

test('no puede enviar a aprobacion con menos de 3 proveedores en la cotización', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id, 'control_verificado' => true]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 5,
    ]);
    // Solo 2 proveedores distintos en toda la requisición: no alcanza el mínimo de 3.
    $precios = RequisicionCotizacionPrecio::factory()->count(2)->create([
        'requisicion_detalle_id' => $detalle->id,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precios->first()->id,
        'proveedor_id' => $precios->first()->proveedor_id,
        'cantidad' => 5,
    ]);
    $req->ocs()->create(['proveedor_id' => $precios->first()->proveedor_id, 'numero_oc' => 1]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/enviar-aprobacion")
        ->assertSessionHasErrors(['cotizaciones']);

    expect($req->fresh()->estatus->value)->toBe('cotizada');
});

test('3 proveedores en total bastan aunque una partida tenga uno solo', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id, 'control_verificado' => true]);

    // 3 partidas, cada una cotizada por un proveedor distinto → 3 proveedores
    // en total, aunque ninguna partida tenga 3. Sin selecciones aún.
    RequisicionDetalle::factory()->count(3)->create(['requisicion_id' => $req->id, 'cantidad' => 5])
        ->each(fn ($detalle) => RequisicionCotizacionPrecio::factory()->create([
            'requisicion_detalle_id' => $detalle->id,
        ]));

    // El gate de proveedores ya pasa (3 distintos); la siguiente falla es por
    // selecciones incompletas, no por cotización.
    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/enviar-aprobacion")
        ->assertSessionHasErrors(['selecciones'])
        ->assertSessionDoesntHaveErrors(['cotizaciones']);
});

test('no puede enviar a aprobacion sin una OC definida', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id, 'control_verificado' => true]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 5,
    ]);
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
    ]);
    RequisicionCotizacionPrecio::factory()->count(2)->create([
        'requisicion_detalle_id' => $detalle->id,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'proveedor_id' => $precio->proveedor_id,
        'cantidad' => 5,
    ]);

    // Selecciones completas pero sin definir ninguna OC.
    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/enviar-aprobacion")
        ->assertSessionHasErrors(['ocs']);

    expect($req->fresh()->estatus->value)->toBe('cotizada');
});

test('no puede enviar a aprobacion si una partida no tiene rubro asignado', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id, 'control_verificado' => true]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => null,
        'cantidad' => 5,
    ]);
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
    ]);
    // 3 proveedores en total para pasar el gate de empresas y llegar al chequeo
    // de centro de costos.
    RequisicionCotizacionPrecio::factory()->count(2)->create([
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
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id, 'control_verificado' => true]);
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

test('el formato comparativo de la requisicion se genera en PDF con verificacion gerencial', function () {
    $req = Requisicion::factory()->cotizada()->create([
        'departamento_id' => $this->depto->id,
        'control_verificado' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.costos.requisiciones.pdf', $req));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('el comparativo no se genera sin verificacion gerencial', function () {
    $req = Requisicion::factory()->cotizada()->create([
        'departamento_id' => $this->depto->id,
        'control_verificado' => false,
    ]);

    $this->actingAs($this->user)
        ->get(route('admin.costos.requisiciones.pdf', $req))
        ->assertForbidden();
});

test('el comparativo colorea solo al proveedor elegido y con IDs tipo texto (PostgreSQL)', function () {
    // En producción (PostgreSQL) PDO devuelve las columnas enteras como texto;
    // este test emula ese tipado para blindar las comparaciones de IDs del blade.
    $req = Requisicion::factory()->cotizada()->create([
        'departamento_id' => $this->depto->id,
        'control_verificado' => true,
    ]);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 5]);

    $elegido = Proveedor::factory()->create();
    $otro = Proveedor::factory()->create();

    $opcElegido = App\Models\Costos\RequisicionCotizacionOpcion::create(['requisicion_id' => $req->id, 'proveedor_id' => $elegido->id, 'orden' => 1]);
    $opcOtro = App\Models\Costos\RequisicionCotizacionOpcion::create(['requisicion_id' => $req->id, 'proveedor_id' => $otro->id, 'orden' => 1]);

    // El proveedor elegido es MÁS CARO ($20) que el otro ($10): se colorea la
    // elección, no el precio más bajo.
    $precioElegido = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $elegido->id,
        'opcion_id' => $opcElegido->id, 'precio_unitario' => 20,
    ]);
    RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $otro->id,
        'opcion_id' => $opcOtro->id, 'precio_unitario' => 10,
    ]);

    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precioElegido->id,
        'proveedor_id' => $elegido->id,
        'cantidad' => 5,
    ]);

    $req->load([
        'solicitante', 'departamento',
        'detalles.cotizaciones.opcion',
        'detalles.selecciones.cotizacionPrecio',
        'detalles.selecciones.proveedor.regimenFiscal',
        'detalles.obraRubro.obra:id,no,descripcion',
        'detalles.obraRubro.rubro:id,codigo,descripcion',
        'cotizacionOpciones.proveedor:id,razon_social,nombre_comercial',
    ]);

    // Emula el tipado de PostgreSQL: IDs/FKs como texto.
    foreach ($req->cotizacionOpciones as $o) {
        $o->id = (string) $o->id;
        $o->proveedor_id = (string) $o->proveedor_id;
    }
    foreach ($req->detalles as $d) {
        foreach ($d->cotizaciones as $c) {
            $c->id = (string) $c->id;
            $c->proveedor_id = (string) $c->proveedor_id;
            $c->opcion_id = (string) $c->opcion_id;
        }
        foreach ($d->selecciones as $s) {
            $s->cotizacion_precio_id = (string) $s->cotizacion_precio_id;
        }
    }

    $html = view('pdf.costos.formato-requisicion-comparativo', [
        'requisicion' => $req,
        'firmas' => collect(),
        'totales' => app(\App\Services\Costos\ComparativoTotalesBuilder::class)->build($req),
    ])->render();

    expect($html)
        ->toContain('opcion seleccionado') // la celda elegida se colorea
        ->not->toContain('#d4edda')        // sin resaltado verde de "mejor opción"
        ->toContain('$100.00');            // importe = precio elegido 5 × 20
});

test('el comparativo aplica retenciones al total neto', function () {
    $req = Requisicion::factory()->cotizada()->create([
        'departamento_id' => $this->depto->id,
        'control_verificado' => true,
    ]);
    // Partida de flete (ISR Fletes 4%) para que haya retención.
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 1,
        'tipo_fiscal' => 'flete',
    ]);
    $proveedor = Proveedor::factory()->create();
    $opcion = App\Models\Costos\RequisicionCotizacionOpcion::create([
        'requisicion_id' => $req->id, 'proveedor_id' => $proveedor->id, 'orden' => 1,
    ]);
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $proveedor->id,
        'opcion_id' => $opcion->id, 'precio_unitario' => 1000,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 1,
    ]);

    $req->load([
        'detalles.selecciones.cotizacionPrecio',
        'detalles.selecciones.proveedor.regimenFiscal',
    ]);

    $totales = app(\App\Services\Costos\ComparativoTotalesBuilder::class)->build($req);

    // Subtotal 1000, IVA 160, ISR Fletes 4% = 40, neto = 1160 - 40 = 1120.
    expect($totales['subtotal'])->toBe(1000.0)
        ->and($totales['iva'])->toBe(160.0)
        ->and($totales['total_retenciones'])->toBe(40.0)
        ->and($totales['neto'])->toBe(1120.0);
});
