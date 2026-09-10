<?php

use App\Enums\Costos\DocumentoTipo;
use App\Models\Costos\ConfiguracionCostos;
use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

afterEach(fn () => Carbon::setTestNow());

function agregarComprobante(Factura $factura): void
{
    $factura->media()->create([
        'descripcion' => DocumentoTipo::ComprobanteRecepcion->value,
        'nombre_original' => 'acuse.pdf',
        'path' => "facturas/{$factura->id}/comprobante/acuse.pdf",
        'mime' => 'application/pdf',
        'size' => 100,
    ]);
}

test('intentarPasarAAprobacion requiere estar entregada Y con comprobante', function () {
    $factura = Factura::factory()->create([
        'estatus' => 'pendiente_recepcion',
        'completamente_entregada' => false,
    ]);

    // Falta todo.
    expect($factura->intentarPasarAAprobacion())->toBeFalse();
    expect($factura->estatus->value)->toBe('pendiente_recepcion');

    // Solo entregada, sin comprobante.
    $factura->update(['completamente_entregada' => true]);
    expect($factura->intentarPasarAAprobacion())->toBeFalse();
    expect($factura->fresh()->estatus->value)->toBe('pendiente_recepcion');

    // Entregada + comprobante → avanza.
    agregarComprobante($factura);
    expect($factura->fresh()->intentarPasarAAprobacion())->toBeTrue();
    expect($factura->fresh()->estatus->value)->toBe('pendiente_aprobacion');
});

test('comprobanteHoyPermitido respeta el día configurado y es libre si es null', function () {
    Carbon::setTestNow('2026-05-27 10:00:00'); // miércoles = dayOfWeek 3
    $config = ConfiguracionCostos::actual();

    $config->update(['dia_comprobante_recepcion' => null]);
    expect($config->fresh()->comprobanteHoyPermitido())->toBeTrue();

    $config->update(['dia_comprobante_recepcion' => 3]); // miércoles
    expect($config->fresh()->comprobanteHoyPermitido())->toBeTrue();

    $config->update(['dia_comprobante_recepcion' => 4]); // jueves
    expect($config->fresh()->comprobanteHoyPermitido())->toBeFalse();
});

test('una entrega que completa la factura la marca entregada y la avanza si ya tiene comprobante', function () {
    $user = User::factory()->create();
    $proveedor = Proveedor::factory()->create();
    $oc = OrdenCompra::factory()->pendienteFactura()->create(['proveedor_id' => $proveedor->id, 'total' => 1000]);
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => 10,
        'precio_unitario' => 100,
        'subtotal' => 1000,
    ]);
    // La factura ampara justo lo que va a entrar: es lo que pide la captura
    // para dejarla ligar a la recepcion.
    $detalles = [['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 10]];
    $factura = facturaQueAmpara(
        Factura::factory()->create([
            'orden_compra_id' => $oc->id,
            'proveedor_id' => $proveedor->id,
            'estatus' => 'pendiente_recepcion',
        ]),
        $oc,
        $detalles,
    );
    agregarComprobante($factura);

    $this->actingAs($user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($user)->id,
            'fecha_entrega' => '2026-05-20',
            'tipo' => 'completa',
            'factura_id' => $factura->id,
            'completa_factura' => true,
            'detalles' => $detalles,
        ])
        ->assertRedirect();

    $factura->refresh();
    expect($factura->completamente_entregada)->toBeTrue();
    expect($factura->estatus->value)->toBe('pendiente_aprobacion');
    expect($factura->entregasLigadas()->count())->toBe(1);
});

test('una entrega que completa la factura sin comprobante la marca pero no la avanza', function () {
    $user = User::factory()->create();
    $proveedor = Proveedor::factory()->create();
    $oc = OrdenCompra::factory()->pendienteFactura()->create(['proveedor_id' => $proveedor->id, 'total' => 1000]);
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => 10,
        'precio_unitario' => 100,
        'subtotal' => 1000,
    ]);
    // La factura ampara justo lo que va a entrar: es lo que pide la captura
    // para dejarla ligar a la recepcion.
    $detalles = [['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 10]];
    $factura = facturaQueAmpara(
        Factura::factory()->create([
            'orden_compra_id' => $oc->id,
            'proveedor_id' => $proveedor->id,
            'estatus' => 'pendiente_recepcion',
        ]),
        $oc,
        $detalles,
    );

    $this->actingAs($user)
        ->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($user)->id,
            'fecha_entrega' => '2026-05-20',
            'tipo' => 'completa',
            'factura_id' => $factura->id,
            'completa_factura' => true,
            'detalles' => $detalles,
        ])
        ->assertRedirect();

    $factura->refresh();
    expect($factura->completamente_entregada)->toBeTrue();
    expect($factura->estatus->value)->toBe('pendiente_recepcion');
});

test('el proveedor sube el comprobante y la factura avanza si ya está entregada', function () {
    Storage::fake('public');
    $proveedor = Proveedor::factory()->create(['tiene_acceso_portal' => true, 'activo' => true]);
    $factura = Factura::factory()->create([
        'proveedor_id' => $proveedor->id,
        'estatus' => 'pendiente_recepcion',
        'completamente_entregada' => true,
    ]);
    ConfiguracionCostos::actual()->update(['dia_comprobante_recepcion' => null]); // libre

    $this->actingAs($proveedor, 'proveedor')
        ->post("/portal/facturas/{$factura->id}/comprobante", [
            'comprobante' => UploadedFile::fake()->create('acuse.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    $factura->refresh();
    expect($factura->tieneComprobanteRecepcion())->toBeTrue();
    expect($factura->estatus->value)->toBe('pendiente_aprobacion');
});

test('el comprobante sin entrega completa no avanza la factura', function () {
    Storage::fake('public');
    $proveedor = Proveedor::factory()->create(['tiene_acceso_portal' => true, 'activo' => true]);
    $factura = Factura::factory()->create([
        'proveedor_id' => $proveedor->id,
        'estatus' => 'pendiente_recepcion',
        'completamente_entregada' => false,
    ]);
    ConfiguracionCostos::actual()->update(['dia_comprobante_recepcion' => null]);

    $this->actingAs($proveedor, 'proveedor')
        ->post("/portal/facturas/{$factura->id}/comprobante", [
            'comprobante' => UploadedFile::fake()->create('acuse.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    $factura->refresh();
    expect($factura->tieneComprobanteRecepcion())->toBeTrue();
    expect($factura->estatus->value)->toBe('pendiente_recepcion');
});

test('el comprobante se bloquea fuera del día configurado', function () {
    Storage::fake('public');
    Carbon::setTestNow('2026-05-28 10:00:00'); // jueves = dayOfWeek 4
    $proveedor = Proveedor::factory()->create(['tiene_acceso_portal' => true, 'activo' => true]);
    $factura = Factura::factory()->create([
        'proveedor_id' => $proveedor->id,
        'estatus' => 'pendiente_recepcion',
        'completamente_entregada' => true,
    ]);
    ConfiguracionCostos::actual()->update(['dia_comprobante_recepcion' => 5]); // viernes

    $this->actingAs($proveedor, 'proveedor')
        ->post("/portal/facturas/{$factura->id}/comprobante", [
            'comprobante' => UploadedFile::fake()->create('acuse.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('comprobante');

    expect($factura->fresh()->tieneComprobanteRecepcion())->toBeFalse();
});

test('la entrega recibe folio REC y genera su PDF de recepción', function () {
    $user = User::factory()->create();
    $oc = OrdenCompra::factory()->pendienteFactura()->create();
    $ocd = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => 5,
        'precio_unitario' => 100,
        'subtotal' => 500,
        'descripcion' => 'Tornillos',
        'codigo_producto' => 'TOR-1',
        'unidad' => 'pza',
    ]);
    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    $entrega->detalles()->create([
        'orden_compra_detalle_id' => $ocd->id,
        'cantidad_recibida' => 5,
        'precio_unitario' => 100,
    ]);

    expect($entrega->folio)->toStartWith('REC-');

    $res = $this->actingAs($user)->get("/admin/costos/entregas/{$entrega->id}/pdf");

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
});

test('un proveedor no puede subir comprobante a factura de otro', function () {
    Storage::fake('public');
    $proveedor = Proveedor::factory()->create(['tiene_acceso_portal' => true, 'activo' => true]);
    $factura = Factura::factory()->create(['estatus' => 'pendiente_recepcion']);

    $this->actingAs($proveedor, 'proveedor')
        ->post("/portal/facturas/{$factura->id}/comprobante", [
            'comprobante' => UploadedFile::fake()->create('acuse.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();
});
