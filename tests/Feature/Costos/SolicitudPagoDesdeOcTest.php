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
    cadenaSolicitudPago($this->depto, 2);
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
    expect($aprobaciones)->toHaveCount(2);
    expect($aprobaciones->pluck('nivel')->all())->toBe([1, 2]);
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

    $this->actingAs($compras)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar", [
            'ocs' => [
                ['proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => 'contado', 'moneda' => 'mxn'],
            ],
        ])
        ->assertRedirect();

    $oc = OrdenCompra::first();
    expect(SolicitudPago::where('orden_compra_id', $oc->id)->count())->toBe(1);
    expect((float) SolicitudPago::first()->monto_total)->toBe((float) $oc->total);
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

    $this->actingAs($compras)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar", [
            'ocs' => [
                ['proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => 'credito', 'moneda' => 'mxn'],
            ],
        ])
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
