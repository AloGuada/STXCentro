<?php

use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Proveedor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow('2026-05-28 10:00:00'); // fecha fija; la factura ya puede subirse cualquier día
    Storage::fake('public');
    $this->proveedor = Proveedor::factory()->create([
        'email' => 'proveedor@test.com',
        'password' => bcrypt('password'),
        'tiene_acceso_portal' => true,
        'activo' => true,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function ocConRecepcion(?int $proveedorId = null, float $total = 11600): OrdenCompra
{
    $oc = OrdenCompra::factory()->pendienteFactura()->create(array_filter([
        'proveedor_id' => $proveedorId,
        'total' => $total,
    ]));
    Entrega::factory()->create(['orden_compra_id' => $oc->id]);

    return $oc->fresh();
}

function cfdiXml(array $overrides = []): string
{
    $attrs = array_merge([
        'Fecha' => '2026-05-20T10:00:00',
        'Folio' => 'F1',
        'SubTotal' => '10000.00',
        'Total' => '11600.00',
        'Moneda' => 'MXN',
        'Uuid' => '11111111-1111-1111-1111-111111111111',
        'RfcEmisor' => 'EME000101AAA',
        'RfcReceptor' => 'REC000101BBB',
        'IvaTrasladado' => '1600.00',
        'IvaRetenido' => '0.00',
        'IsrRetenido' => '0.00',
    ], $overrides);

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante
    xmlns:cfdi="http://www.sat.gob.mx/cfd/4"
    xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital"
    Version="4.0"
    Fecha="{$attrs['Fecha']}"
    Folio="{$attrs['Folio']}"
    SubTotal="{$attrs['SubTotal']}"
    Total="{$attrs['Total']}"
    Moneda="{$attrs['Moneda']}">
  <cfdi:Emisor Rfc="{$attrs['RfcEmisor']}" Nombre="Emisor SA" RegimenFiscal="601"/>
  <cfdi:Receptor Rfc="{$attrs['RfcReceptor']}" Nombre="Receptor SA" UsoCFDI="G03"/>
  <cfdi:Impuestos TotalImpuestosTrasladados="{$attrs['IvaTrasladado']}" TotalImpuestosRetenidos="{$attrs['IvaRetenido']}">
    <cfdi:Retenciones>
      <cfdi:Retencion Impuesto="002" Importe="{$attrs['IvaRetenido']}"/>
      <cfdi:Retencion Impuesto="001" Importe="{$attrs['IsrRetenido']}"/>
    </cfdi:Retenciones>
    <cfdi:Traslados>
      <cfdi:Traslado Base="{$attrs['SubTotal']}" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="{$attrs['IvaTrasladado']}"/>
    </cfdi:Traslados>
  </cfdi:Impuestos>
  <cfdi:Complemento>
    <tfd:TimbreFiscalDigital UUID="{$attrs['Uuid']}" FechaTimbrado="{$attrs['Fecha']}"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;
}

function uploadXml(array $overrides = []): UploadedFile
{
    return UploadedFile::fake()->createWithContent('factura.xml', cfdiXml($overrides));
}

test('lista facturas del proveedor', function () {
    Factura::factory()->count(2)->create(['proveedor_id' => $this->proveedor->id]);
    Factura::factory()->create();

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal/facturas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/facturas/index')
            ->has('facturas.data', 2)
        );
});

test('flujo two-step: preview parsea XML y store crea factura', function () {
    $oc = ocConRecepcion($this->proveedor->id, total: 20000);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas/preview', [
            'orden_compra_id' => $oc->id,
            'xml' => uploadXml(['Uuid' => 'AAAA1111-AAAA-AAAA-AAAA-AAAAAAAAAAAA']),
        ])
        ->assertRedirect('/portal/facturas/preview');

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal/facturas/preview')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/facturas/preview')
            ->where('fiscal.uuid_fiscal', 'AAAA1111-AAAA-AAAA-AAAA-AAAAAAAAAAAA')
            ->where('fiscal.total', 11600)
        );

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas', ['notas' => 'desde preview'])
        ->assertRedirect('/portal/facturas');

    $this->assertDatabaseHas('costos_facturas', [
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $this->proveedor->id,
        'estatus' => 'pendiente_recepcion',
        'uuid_fiscal' => 'AAAA1111-AAAA-AAAA-AAAA-AAAAAAAAAAAA',
        'notas' => 'desde preview',
    ]);
});

test('permite subir factura aunque la OC no tenga recepción previa', function () {
    $oc = OrdenCompra::factory()->pendienteEntrega()->create([
        'proveedor_id' => $this->proveedor->id,
        'total' => 20000,
    ]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas/preview', [
            'orden_compra_id' => $oc->id,
            'xml' => uploadXml(['Uuid' => 'BBBB1111-BBBB-BBBB-BBBB-BBBBBBBBBBBB']),
        ])
        ->assertRedirect('/portal/facturas/preview');

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas')
        ->assertRedirect('/portal/facturas');

    $this->assertDatabaseHas('costos_facturas', [
        'orden_compra_id' => $oc->id,
        'estatus' => 'pendiente_recepcion',
    ]);
});

test('preview bloquea cuando el CFDI excede el saldo facturable', function () {
    $oc = ocConRecepcion($this->proveedor->id, total: 5000);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas/preview', [
            'orden_compra_id' => $oc->id,
            'xml' => uploadXml(['Total' => '11600.00', 'SubTotal' => '10000.00']),
        ])
        ->assertSessionHasErrors(['xml']);
});

test('preview bloquea UUID fiscal duplicado', function () {
    Factura::factory()->create([
        'proveedor_id' => $this->proveedor->id,
        'uuid_fiscal' => 'DUPDUPDU-DUPD-DUPD-DUPD-DUPDUPDUPDUP',
    ]);
    $oc = ocConRecepcion($this->proveedor->id);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas/preview', [
            'orden_compra_id' => $oc->id,
            'xml' => uploadXml(['Uuid' => 'DUPDUPDU-DUPD-DUPD-DUPD-DUPDUPDUPDUP']),
        ])
        ->assertSessionHasErrors(['xml']);
});

test('preview rechaza OC de otro proveedor', function () {
    $oc = ocConRecepcion();

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas/preview', [
            'orden_compra_id' => $oc->id,
            'xml' => uploadXml(),
        ])
        ->assertForbidden();
});

test('preview valida XML obligatorio', function () {
    $oc = ocConRecepcion($this->proveedor->id);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas/preview', [
            'orden_compra_id' => $oc->id,
        ])
        ->assertSessionHasErrors(['xml']);
});

test('cancel-preview limpia session y redirige a OC', function () {
    $oc = ocConRecepcion($this->proveedor->id, total: 20000);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas/preview', [
            'orden_compra_id' => $oc->id,
            'xml' => uploadXml(['Uuid' => 'CAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA']),
        ])
        ->assertRedirect('/portal/facturas/preview');

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas/cancel-preview')
        ->assertRedirect("/portal/ordenes-compra/{$oc->id}");

    expect(Factura::count())->toBe(0);
});

test('store sin preview previo redirige a OCs', function () {
    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas', ['notas' => 'sin preview'])
        ->assertRedirect('/portal/ordenes-compra');
});

test('muestra detalle de factura propia', function () {
    $factura = Factura::factory()->create(['proveedor_id' => $this->proveedor->id]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/facturas/{$factura->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/facturas/show')
            ->where('factura.id', $factura->id)
        );
});

test('no puede ver factura de otro proveedor', function () {
    $factura = Factura::factory()->create();

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/facturas/{$factura->id}")
        ->assertForbidden();
});

test('desde el tablero el paso 2 se confirma en el propio tablero', function () {
    $oc = ocConRecepcion($this->proveedor->id, total: 20000);

    // Paso 1: no manda a la pantalla de preview, regresa al tablero.
    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas/preview', [
            'orden_compra_id' => $oc->id,
            'xml' => uploadXml(['Uuid' => 'DAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA']),
            'origen' => 'tablero',
        ])
        ->assertRedirect('/portal');

    // El tablero trae los datos del CFDI para pintarlos en el modal.
    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('facturaPreview.fiscal.uuid_fiscal', 'DAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA')
            ->where('facturaPreview.orden_compra_folio', $oc->folio)
        );

    // La pantalla vieja de preview ya no aplica para este flujo.
    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal/facturas/preview')
        ->assertRedirect('/portal');

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas', ['notas' => 'desde el tablero'])
        ->assertRedirect('/portal');

    $this->assertDatabaseHas('costos_facturas', [
        'orden_compra_id' => $oc->id,
        'uuid_fiscal' => 'DAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA',
        'notas' => 'desde el tablero',
    ]);
});

test('cancelar el preview del tablero vuelve al tablero y borra los temporales', function () {
    $oc = ocConRecepcion($this->proveedor->id, total: 20000);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas/preview', [
            'orden_compra_id' => $oc->id,
            'xml' => uploadXml(['Uuid' => 'EAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA']),
            'origen' => 'tablero',
        ]);

    expect(Storage::disk('public')->allFiles("tmp_facturas/{$this->proveedor->id}"))->not->toBeEmpty();

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas/cancel-preview')
        ->assertRedirect('/portal');

    expect(Factura::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles("tmp_facturas/{$this->proveedor->id}"))->toBeEmpty();
});

test('sin preview en curso el tablero no abre el modal de confirmación', function () {
    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal')
        ->assertInertia(fn ($page) => $page->where('facturaPreview', null));
});
