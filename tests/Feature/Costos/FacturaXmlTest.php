<?php

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\SolicitudPago;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\Costos\CfdiXmlParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function sampleCfdi(array $overrides = []): string
{
    $attrs = array_merge([
        'Fecha' => '2026-04-24T10:00:00',
        'Folio' => 'A100',
        'SubTotal' => '1000.00',
        'Total' => '1096.00',
        'Moneda' => 'MXN',
        'Uuid' => 'AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA',
        'RfcEmisor' => 'EME000101AAA',
        'RfcReceptor' => 'REC000101BBB',
        'IvaTrasladado' => '160.00',
        'IvaRetenido' => '64.00',
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
      <cfdi:Traslado Base="1000.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="{$attrs['IvaTrasladado']}"/>
    </cfdi:Traslados>
  </cfdi:Impuestos>
  <cfdi:Complemento>
    <tfd:TimbreFiscalDigital UUID="{$attrs['Uuid']}" FechaTimbrado="2026-04-24T10:01:00"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;
}

describe('CfdiXmlParser', function () {
    test('extrae datos fiscales basicos del XML', function () {
        $parser = new CfdiXmlParser;
        $result = $parser->parse(sampleCfdi());

        expect($result['uuid_fiscal'])->toBe('AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA');
        expect($result['folio_fiscal'])->toBe('A100');
        expect($result['fecha_factura'])->toBe('2026-04-24');
        expect($result['subtotal'])->toBe(1000.0);
        expect($result['total'])->toBe(1096.0);
        expect($result['rfc_emisor'])->toBe('EME000101AAA');
        expect($result['rfc_receptor'])->toBe('REC000101BBB');
    });

    test('suma IVA trasladado, IVA retenido e ISR retenido', function () {
        $parser = new CfdiXmlParser;
        $result = $parser->parse(sampleCfdi([
            'IvaTrasladado' => '160.00',
            'IvaRetenido' => '64.00',
            'IsrRetenido' => '100.00',
        ]));

        expect($result['iva_trasladado'])->toBe(160.0);
        expect($result['iva_retenido'])->toBe(64.0);
        expect($result['isr_retenido'])->toBe(100.0);
    });

    test('guarda detalle de traslados y retenciones en JSON', function () {
        $parser = new CfdiXmlParser;
        $result = $parser->parse(sampleCfdi());

        expect($result['impuestos_detalle']['traslados'])->toHaveCount(1);
        expect($result['impuestos_detalle']['traslados'][0]['impuesto'])->toBe('002');
        expect($result['impuestos_detalle']['traslados'][0]['tasa'])->toBe('0.160000');
        expect($result['impuestos_detalle']['retenciones'])->toHaveCount(2);
    });

    test('lanza excepcion con XML invalido', function () {
        $parser = new CfdiXmlParser;

        expect(fn () => $parser->parse('<<<no-es-xml>>>'))
            ->toThrow(\RuntimeException::class);
    });
});

describe('Portal upload XML auto-llena datos fiscales', function () {
    beforeEach(function () {
        Carbon::setTestNow('2026-05-28 10:00:00'); // jueves: carga de facturas permitida
        Storage::fake('public');
        $this->proveedor = Proveedor::factory()->create([
            'email' => 'xml-proveedor@test.com',
            'password' => bcrypt('password'),
            'tiene_acceso_portal' => true,
            'activo' => true,
        ]);
    });

    afterEach(fn () => Carbon::setTestNow());

    test('two-step: preview parsea XML y store crea factura con datos fiscales', function () {
        $oc = OrdenCompra::factory()->pendienteFactura()->create([
            'proveedor_id' => $this->proveedor->id,
            'total' => 5000,
        ]);
        Entrega::factory()->create(['orden_compra_id' => $oc->id]);

        $xml = UploadedFile::fake()->createWithContent('factura.xml', sampleCfdi([
            'Uuid' => 'BBBBBBBB-BBBB-BBBB-BBBB-BBBBBBBBBBBB',
            'Folio' => 'B200',
            'SubTotal' => '2000.00',
            'Total' => '2320.00',
            'IvaTrasladado' => '320.00',
            'IvaRetenido' => '0.00',
            'IsrRetenido' => '0.00',
        ]));

        $this->actingAs($this->proveedor, 'proveedor')
            ->post('/portal/facturas/preview', [
                'orden_compra_id' => $oc->id,
                'xml' => $xml,
            ])
            ->assertRedirect('/portal/facturas/preview');

        $this->actingAs($this->proveedor, 'proveedor')
            ->post('/portal/facturas')
            ->assertRedirect('/portal/facturas');

        $factura = Factura::where('proveedor_id', $this->proveedor->id)->first();
        expect($factura)->not->toBeNull();
        expect($factura->uuid_fiscal)->toBe('BBBBBBBB-BBBB-BBBB-BBBB-BBBBBBBBBBBB');
        expect($factura->folio_fiscal)->toBe('B200');
        expect((float) $factura->subtotal)->toBe(2000.0);
        expect((float) $factura->total)->toBe(2320.0);
        expect((float) $factura->iva_trasladado)->toBe(320.0);
        expect($factura->impuestos_detalle)->toBeArray();

        $this->assertDatabaseHas('media', [
            'mediable_type' => Factura::class,
            'descripcion' => DocumentoTipo::XmlFactura->value,
        ]);
    });

    test('preview rechaza XML malformado', function () {
        $oc = OrdenCompra::factory()->pendienteFactura()->create(['proveedor_id' => $this->proveedor->id]);
        Entrega::factory()->create(['orden_compra_id' => $oc->id]);
        $xml = UploadedFile::fake()->createWithContent('factura.xml', '<<<no es xml>>>');

        $this->actingAs($this->proveedor, 'proveedor')
            ->post('/portal/facturas/preview', [
                'orden_compra_id' => $oc->id,
                'xml' => $xml,
            ])
            ->assertSessionHasErrors(['xml']);
    });
});

describe('subir factura de contado (admin / Compras)', function () {
    beforeEach(function () {
        Storage::fake('public');
        Permission::firstOrCreate(['name' => 'costos.facturas.crear', 'guard_name' => 'web']);
        $this->compras = User::factory()->create();
        $this->compras->givePermissionTo('costos.facturas.crear');
    });

    test('compras sube CFDI de OC contado y registra la factura con datos fiscales', function () {
        $oc = OrdenCompra::factory()->pendienteFactura()->create(['tipo_pago' => 'contado', 'total' => 2320]);
        Entrega::factory()->create(['orden_compra_id' => $oc->id]);
        // Anticipo ya pagado vía solicitud → la factura debe nacer Pagada.
        SolicitudPago::factory()->create([
            'orden_compra_id' => $oc->id,
            'departamento_id' => Departamento::factory(),
            'estatus' => SolicitudPagoEstatus::Pagada->value,
        ]);

        $xml = UploadedFile::fake()->createWithContent('factura.xml', sampleCfdi([
            'Uuid' => 'CCCCCCCC-CCCC-CCCC-CCCC-CCCCCCCCCCCC',
            'SubTotal' => '2000.00', 'Total' => '2320.00', 'IvaTrasladado' => '320.00',
        ]));
        $pdf = UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf');

        $this->actingAs($this->compras)
            ->post("/admin/costos/ordenes-compra/{$oc->id}/factura-contado", ['xml' => $xml, 'pdf' => $pdf])
            ->assertRedirect();

        $factura = Factura::where('orden_compra_id', $oc->id)->first();
        expect($factura)->not->toBeNull()
            ->and($factura->uuid_fiscal)->toBe('CCCCCCCC-CCCC-CCCC-CCCC-CCCCCCCCCCCC')
            ->and((float) $factura->total)->toBe(2320.0)
            ->and((float) $factura->iva_trasladado)->toBe(320.0)
            ->and($factura->estatus)->toBe(FacturaEstatus::Pagada)
            ->and($factura->aprobada_costos)->toBeTrue()
            ->and($factura->aceptada_contabilidad)->toBeTrue();

        $this->assertDatabaseHas('media', ['mediable_type' => Factura::class, 'descripcion' => DocumentoTipo::XmlFactura->value]);
        $this->assertDatabaseHas('media', ['mediable_type' => Factura::class, 'descripcion' => DocumentoTipo::PdfFactura->value]);
    });

    test('rechaza un CFDI cuyo total excede el saldo facturable de la OC', function () {
        $oc = OrdenCompra::factory()->pendienteFactura()->create(['tipo_pago' => 'contado', 'total' => 100]);
        Entrega::factory()->create(['orden_compra_id' => $oc->id]);

        $xml = UploadedFile::fake()->createWithContent('factura.xml', sampleCfdi([
            'Uuid' => 'AAAA1111-AAAA-AAAA-AAAA-AAAAAAAAAAAA', 'Total' => '5000.00',
        ]));
        $pdf = UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf');

        $this->actingAs($this->compras)
            ->post("/admin/costos/ordenes-compra/{$oc->id}/factura-contado", ['xml' => $xml, 'pdf' => $pdf])
            ->assertSessionHasErrors(['xml']);

        expect(Factura::where('orden_compra_id', $oc->id)->count())->toBe(0);
    });

    test('rechaza un UUID fiscal duplicado', function () {
        $oc = OrdenCompra::factory()->pendienteFactura()->create(['tipo_pago' => 'contado']);
        Entrega::factory()->create(['orden_compra_id' => $oc->id]);
        Factura::factory()->create(['uuid_fiscal' => 'DDDDDDDD-DDDD-DDDD-DDDD-DDDDDDDDDDDD']);

        $xml = UploadedFile::fake()->createWithContent('factura.xml', sampleCfdi(['Uuid' => 'DDDDDDDD-DDDD-DDDD-DDDD-DDDDDDDDDDDD']));
        $pdf = UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf');

        $this->actingAs($this->compras)
            ->post("/admin/costos/ordenes-compra/{$oc->id}/factura-contado", ['xml' => $xml, 'pdf' => $pdf])
            ->assertSessionHasErrors(['xml']);
    });

    test('exige recepción previa del almacén', function () {
        $oc = OrdenCompra::factory()->pendienteEntrega()->create(['tipo_pago' => 'contado']);

        $xml = UploadedFile::fake()->createWithContent('factura.xml', sampleCfdi(['Uuid' => 'FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF']));
        $pdf = UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf');

        $this->actingAs($this->compras)
            ->post("/admin/costos/ordenes-compra/{$oc->id}/factura-contado", ['xml' => $xml, 'pdf' => $pdf])
            ->assertSessionHasErrors(['xml']);
    });

    test('rechaza una OC de crédito', function () {
        $oc = OrdenCompra::factory()->pendienteFactura()->create(['tipo_pago' => 'credito']);
        Entrega::factory()->create(['orden_compra_id' => $oc->id]);

        $xml = UploadedFile::fake()->createWithContent('factura.xml', sampleCfdi(['Uuid' => 'EEEEEEEE-EEEE-EEEE-EEEE-EEEEEEEEEEEE']));
        $pdf = UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf');

        $this->actingAs($this->compras)
            ->post("/admin/costos/ordenes-compra/{$oc->id}/factura-contado", ['xml' => $xml, 'pdf' => $pdf])
            ->assertSessionHasErrors(['xml']);
    });
});

test('el registro de factura CFDI es atómico: si falla el adjunto no queda factura ni media', function () {
    $oc = OrdenCompra::factory()->pendienteFactura()->create();

    expect(fn () => app(\App\Services\Costos\RegistradorFacturaCfdi::class)->registrar(
        $oc,
        ['total' => 100, 'uuid_fiscal' => 'ATOMICO1-AAAA-AAAA-AAAA-AAAAAAAAAAAA'],
        ['estatus' => FacturaEstatus::PendienteAprobacion->value],
        function () {
            throw new RuntimeException('falla simulada al adjuntar');
        },
    ))->toThrow(RuntimeException::class);

    expect(Factura::where('uuid_fiscal', 'ATOMICO1-AAAA-AAAA-AAAA-AAAAAAAAAAAA')->exists())->toBeFalse();
});
