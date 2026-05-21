<?php

use App\Enums\Costos\DocumentoTipo;
use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Proveedor;
use App\Services\Costos\CfdiXmlParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
        Storage::fake('public');
        $this->proveedor = Proveedor::factory()->create([
            'email' => 'xml-proveedor@test.com',
            'password' => bcrypt('password'),
            'tiene_acceso_portal' => true,
            'activo' => true,
        ]);
    });

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
