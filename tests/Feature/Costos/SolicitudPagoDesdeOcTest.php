<?php

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\Factura;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Pago;
use App\Models\Costos\Permiso;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Costos\SolicitudPago;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\Costos\SolicitudPagoDesdeOrdenCompra;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('public');
    $this->depto = Departamento::factory()->create();
});

function ocContadoConDetalle(Departamento $depto, ObraRubro $rubro, float $precio = 100, float $cantidad = 5): OrdenCompra
{
    $oc = OrdenCompra::factory()->pendienteEntrega()->create([
        'departamento_id' => $depto->id,
        'tipo_pago' => 'contado',
        'total' => round($precio * $cantidad * 1.16, 2),
    ]);

    OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'obra_rubro_id' => $rubro->id,
        'descripcion' => 'Tornillos',
        'cantidad' => $cantidad,
        'precio_unitario' => $precio,
        'subtotal' => round($precio * $cantidad, 2),
    ]);

    return $oc->fresh();
}

function cadenaSolicitudPago(Departamento $depto, int $niveles = 2): void
{
    for ($i = 1; $i <= $niveles; $i++) {
        $permiso = Permiso::factory()->create(['tipo_aprobacion' => SolicitudPago::TIPO_APROBACION, 'nivel' => $i]);
        AprobacionDepartamento::factory()->create([
            'departamento_id' => $depto->id,
            'permiso_id' => $permiso->id,
        ]);
    }
}

test('genera solicitud completa desde OC de contado', function () {
    $rubro = ObraRubro::factory()->create();
    cadenaSolicitudPago($this->depto, 2); // niveles del depto: deben ignorarse (firma única del gerente)
    $gerente = User::factory()->create();
    \App\Models\Costos\ConfiguracionCostos::actual()->update(['gerente_compras_id' => $gerente->id]);
    $oc = ocContadoConDetalle($this->depto, $rubro);
    $userId = User::factory()->create()->id;

    $solicitud = app(SolicitudPagoDesdeOrdenCompra::class)->crear($oc, $userId);

    expect($solicitud)->not->toBeNull();
    expect($solicitud->estatus)->toBe(SolicitudPagoEstatus::PendienteFirma);
    expect($solicitud->orden_compra_id)->toBe($oc->id);
    expect($solicitud->departamento_id)->toBe($this->depto->id);
    expect($solicitud->proveedor_id)->toBe($oc->proveedor_id);
    expect((float) $solicitud->monto_total)->toBe((float) $oc->total);

    expect($solicitud->detalles()->count())->toBe(1);
    expect($solicitud->detalles()->first()->obra_rubro_id)->toBe($rubro->id);

    $archivo = $solicitud->archivos()->first();
    expect($archivo)->not->toBeNull();
    expect($archivo->archivo_id)->toBeNull();
    expect($archivo->media->descripcion)->toBe(DocumentoTipo::OcPdfFormato->value);
    Storage::disk('public')->assertExists($archivo->media->path);

    $aprobaciones = $solicitud->aprobaciones()->orderBy('nivel')->get();
    expect($aprobaciones)->toHaveCount(1);
    expect($aprobaciones->first()->aprobador_id)->toBe($gerente->id);
});

test('liberar OC de contado crea solicitud vinculada; credito no', function () {
    $permLiberar = Permission::firstOrCreate(['name' => 'costos.requisiciones.liberar', 'guard_name' => 'web']);
    $compras = User::factory()->create();
    $compras->givePermissionTo($permLiberar);

    cadenaSolicitudPago($this->depto, 1);

    $rubro = ObraRubro::factory()->create();
    $req = Requisicion::factory()->aprobada()->create(['departamento_id' => $this->depto->id]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => $rubro->id,
        'cantidad' => 10,
    ]);
    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
        'precio_unitario' => 25.00,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'numero_oc' => 1,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 10,
    ]);
    \App\Models\Costos\RequisicionOc::create([
        'requisicion_id' => $req->id, 'proveedor_id' => $proveedor->id, 'numero_oc' => 1,
        'modo_pago' => 'contado', 'fecha_entrega' => now()->addDays(7)->format('Y-m-d'),
    ]);

    $this->actingAs($compras)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar")
        ->assertRedirect();

    $oc = OrdenCompra::first();
    expect(SolicitudPago::where('orden_compra_id', $oc->id)->count())->toBe(1);
    expect((float) SolicitudPago::first()->monto_total)->toBe((float) $oc->total);
});

test('genera N solicitudes para una OC de contado con parcialidades', function () {
    $rubro = ObraRubro::factory()->create();
    \App\Models\Costos\ConfiguracionCostos::actual()->update(['gerente_compras_id' => User::factory()->create()->id]);
    $oc = ocContadoConDetalle($this->depto, $rubro, 100, 5); // total = 580
    $userId = User::factory()->create()->id;

    app(SolicitudPagoDesdeOrdenCompra::class)->crearParcialidades($oc, [
        ['porcentaje' => 50, 'concepto' => 'Anticipo'],
        ['porcentaje' => 50, 'concepto' => 'Contra entrega'],
    ], $userId);

    $solicitudes = SolicitudPago::where('orden_compra_id', $oc->id)->orderBy('id')->get();
    expect($solicitudes)->toHaveCount(2);
    expect((float) $solicitudes[0]->monto_total)->toBe(290.0)
        ->and((float) $solicitudes[1]->monto_total)->toBe(290.0)
        ->and($solicitudes[0]->concepto)->toContain('Anticipo')
        ->and($solicitudes[1]->concepto)->toContain('Contra entrega')
        ->and($solicitudes[0]->aprobaciones()->count())->toBe(1);
});

test('la solicitud usa el método de pago elegido en la OC', function () {
    $rubro = ObraRubro::factory()->create();
    $oc = ocContadoConDetalle($this->depto, $rubro);
    $userId = User::factory()->create()->id;

    $solicitud = app(SolicitudPagoDesdeOrdenCompra::class)->crear($oc, $userId, 'cheque');

    expect($solicitud->tipo_pago)->toBe('cheque');
});

test('la solicitud usa la fecha de pago indicada en la OC', function () {
    // Con el reloj quieto: la fecha elegida por compras sólo se respeta si es
    // de hoy en adelante, así que amarrarla a un día del calendario hacía que
    // el test caducara al pasar esa fecha.
    Carbon::setTestNow(Carbon::parse('2026-08-12 10:00')); // miércoles

    $rubro = ObraRubro::factory()->create();
    $oc = ocContadoConDetalle($this->depto, $rubro);
    $userId = User::factory()->create()->id;

    // El viernes de esta misma semana, aunque el corte del miércoles ya pasó.
    $solicitud = app(SolicitudPagoDesdeOrdenCompra::class)->crear($oc, $userId, 'transferencia', '2026-08-14');

    expect((string) $solicitud->fecha_pago_solicitada)->toContain('2026-08-14');

    Carbon::setTestNow();
});

test('si la fecha que trae la OC ya pasó, la solicitud cae al próximo viernes', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-18 10:00')); // martes

    $rubro = ObraRubro::factory()->create();
    $oc = ocContadoConDetalle($this->depto, $rubro);
    $userId = User::factory()->create()->id;

    // El viernes anterior: ya no se puede pagar ahí.
    $solicitud = app(SolicitudPagoDesdeOrdenCompra::class)->crear($oc, $userId, 'transferencia', '2026-08-14');

    expect((string) $solicitud->fecha_pago_solicitada)->toContain('2026-08-21');

    Carbon::setTestNow();
});

test('la solicitud usa el próximo viernes si la OC no indica fecha de pago', function () {
    $rubro = ObraRubro::factory()->create();
    $oc = ocContadoConDetalle($this->depto, $rubro);
    $userId = User::factory()->create()->id;

    $solicitud = app(SolicitudPagoDesdeOrdenCompra::class)->crear($oc, $userId, 'transferencia', null);

    $esperada = \App\Models\Costos\ConfiguracionCostos::actual()
        ->proximoViernes(\Carbon\CarbonImmutable::now()->startOfDay())
        ->toDateString();
    expect((string) $solicitud->fecha_pago_solicitada)->toContain($esperada);
});

test('liberar OC de contado con parcialidades genera una solicitud por hito', function () {
    $permLiberar = Permission::firstOrCreate(['name' => 'costos.requisiciones.liberar', 'guard_name' => 'web']);
    $compras = User::factory()->create();
    $compras->givePermissionTo($permLiberar);
    cadenaSolicitudPago($this->depto, 1);

    $rubro = ObraRubro::factory()->create();
    $req = Requisicion::factory()->aprobada()->create(['departamento_id' => $this->depto->id]);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'obra_rubro_id' => $rubro->id, 'cantidad' => 10]);
    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $proveedor->id, 'precio_unitario' => 25.00,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id, 'cotizacion_precio_id' => $precio->id,
        'numero_oc' => 1, 'proveedor_id' => $proveedor->id, 'cantidad' => 10,
    ]);
    \App\Models\Costos\RequisicionOc::create([
        'requisicion_id' => $req->id, 'proveedor_id' => $proveedor->id, 'numero_oc' => 1,
        'modo_pago' => 'contado', 'fecha_entrega' => now()->addDays(7)->format('Y-m-d'),
        'pagos' => [['porcentaje' => 60, 'concepto' => 'Anticipo'], ['porcentaje' => 40, 'concepto' => 'Liquidación']],
    ]);

    $this->actingAs($compras)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar")
        ->assertRedirect();

    $oc = OrdenCompra::first();
    $sols = SolicitudPago::where('orden_compra_id', $oc->id)->orderBy('id')->get();
    // total = 25 * 10 * 1.16 = 290; 60% = 174, 40% = 116
    expect($sols)->toHaveCount(2)
        ->and((float) $sols[0]->monto_total)->toBe(174.0)
        ->and((float) $sols[1]->monto_total)->toBe(116.0);
});

test('OC de credito no genera solicitud de pago', function () {
    $permLiberar = Permission::firstOrCreate(['name' => 'costos.requisiciones.liberar', 'guard_name' => 'web']);
    $compras = User::factory()->create();
    $compras->givePermissionTo($permLiberar);

    $rubro = ObraRubro::factory()->create();
    $req = Requisicion::factory()->aprobada()->create(['departamento_id' => $this->depto->id]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => $rubro->id,
        'cantidad' => 10,
    ]);
    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
        'precio_unitario' => 25.00,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'numero_oc' => 1,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 10,
    ]);
    \App\Models\Costos\RequisicionOc::create([
        'requisicion_id' => $req->id, 'proveedor_id' => $proveedor->id, 'numero_oc' => 1,
        'modo_pago' => 'credito', 'fecha_entrega' => now()->addDays(7)->format('Y-m-d'),
    ]);

    $this->actingAs($compras)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar")
        ->assertRedirect();

    expect(SolicitudPago::count())->toBe(0);
});

test('no duplica afectacion presupuestal y refleja pagada al pagarse la solicitud', function () {
    $rubro = ObraRubro::factory()->create(['acumulado' => 0]);
    $oc = ocContadoConDetalle($this->depto, $rubro, 100, 5);
    $userId = User::factory()->create()->id;

    $oc->load('detalles');
    $oc->aplicarImpactoPresupuestal($userId);
    $rubro->refresh();
    expect((float) $rubro->acumulado)->toBe(500.0);

    $solicitud = app(SolicitudPagoDesdeOrdenCompra::class)->crear($oc, $userId);

    // La solicitud NO debe apartar ni afectar el presupuesto: la OC ya lo hizo.
    $rubro->refresh();
    expect((float) $rubro->acumulado)->toBe(500.0);

    $solicitud->onAprobacionCompleta($userId);
    $rubro->refresh();
    expect((float) $rubro->acumulado)->toBe(500.0);

    $solicitud->transitionTo(SolicitudPagoEstatus::Pagada);
    expect($oc->fresh()->pagadaAnticipoContado())->toBeTrue();
});

test('factura posterior de OC contado pagada no genera segundo pago', function () {
    $perm = Permission::firstOrCreate(['name' => 'costos.facturas.aceptar-contabilidad', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->givePermissionTo($perm);

    $proveedor = Proveedor::factory()->create(['email' => null]);
    $oc = OrdenCompra::factory()->pendienteFactura()->create([
        'departamento_id' => $this->depto->id,
        'proveedor_id' => $proveedor->id,
        'tipo_pago' => 'contado',
    ]);
    SolicitudPago::factory()->create([
        'orden_compra_id' => $oc->id,
        'departamento_id' => $this->depto->id,
        'estatus' => SolicitudPagoEstatus::Pagada->value,
    ]);

    $factura = Factura::factory()->pendientePago()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
        'fecha_factura' => '2026-02-17',
    ]);

    $this->actingAs($user)
        ->post("/admin/costos/facturas/{$factura->id}/aceptar-contabilidad")
        ->assertRedirect();

    $factura->refresh();
    expect($factura->aceptada_contabilidad)->toBeTrue();
    // La factura de contado queda Pagada (cubierta por el anticipo), no atorada en pendiente_pago.
    expect($factura->estatus)->toBe(\App\Enums\Costos\FacturaEstatus::Pagada);
    expect(Pago::where('pagable_type', Factura::class)->where('pagable_id', $factura->id)->count())->toBe(0);
});

test('es idempotente: no crea una segunda solicitud para la misma OC', function () {
    $rubro = ObraRubro::factory()->create();
    cadenaSolicitudPago($this->depto, 1);
    $oc = ocContadoConDetalle($this->depto, $rubro);
    $userId = User::factory()->create()->id;

    $service = app(SolicitudPagoDesdeOrdenCompra::class);
    $service->crear($oc, $userId);
    $segunda = $service->crear($oc->fresh(), $userId);

    expect($segunda)->toBeNull();
    expect(SolicitudPago::where('orden_compra_id', $oc->id)->count())->toBe(1);
});

test('departamento sin cadena de aprobacion no aborta la creacion', function () {
    $rubro = ObraRubro::factory()->create();
    $oc = ocContadoConDetalle($this->depto, $rubro);
    $userId = User::factory()->create()->id;

    $solicitud = app(SolicitudPagoDesdeOrdenCompra::class)->crear($oc, $userId);

    expect($solicitud)->not->toBeNull();
    expect($solicitud->aprobaciones()->count())->toBe(0);
});
