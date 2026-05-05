<?php

use App\Enums\Costos\DocumentoTipo;
use App\Models\Costos\AnticipoAplicacion;
use App\Models\Costos\Factura;
use App\Models\Costos\NotaCredito;
use App\Models\Proveedor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->proveedor = Proveedor::factory()->create([
        'email' => 'proveedor@test.com',
        'password' => bcrypt('password'),
        'tiene_acceso_portal' => true,
        'activo' => true,
    ]);
});

test('proveedor sube nota de crédito sobre su propia factura', function () {
    $factura = Factura::factory()->create([
        'proveedor_id' => $this->proveedor->id,
        'total' => 10000,
    ]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/notas-credito', [
            'factura_id' => $factura->id,
            'monto' => 1500,
            'concepto' => 'Descuento por pronto pago',
            'fecha_emision' => '2026-04-26',
        ])
        ->assertRedirect();

    $nota = NotaCredito::first();
    expect($nota)->not->toBeNull();
    expect($nota->folio)->toStartWith('NC-');
    expect((float) $nota->monto)->toBe(1500.0);
    expect($nota->estatus->value)->toBe('vigente');
    expect($nota->creado_por)->toBeNull();

    $factura->refresh();
    expect((float) $factura->monto_notas_credito)->toBe(1500.0);
    expect((float) $factura->saldo_facturado)->toBe(8500.0);
});

test('proveedor no puede subir nota sobre factura ajena', function () {
    $facturaAjena = Factura::factory()->create(['total' => 5000]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/notas-credito', [
            'factura_id' => $facturaAjena->id,
            'monto' => 100,
            'concepto' => 'Intento ilícito',
            'fecha_emision' => '2026-04-26',
        ])
        ->assertForbidden();

    expect(NotaCredito::count())->toBe(0);
});

test('rechaza nota cuyo monto excede el saldo facturado disponible', function () {
    $factura = Factura::factory()->create([
        'proveedor_id' => $this->proveedor->id,
        'total' => 5000,
    ]);
    NotaCredito::factory()->create([
        'factura_id' => $factura->id,
        'monto' => 3000,
    ]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/notas-credito', [
            'factura_id' => $factura->id,
            'monto' => 2500, // 3000 + 2500 = 5500 > 5000
            'concepto' => 'Otro descuento',
            'fecha_emision' => '2026-04-26',
        ])
        ->assertSessionHasErrors(['monto']);
});

test('considera anticipos al calcular saldo facturado disponible', function () {
    $factura = Factura::factory()->create([
        'proveedor_id' => $this->proveedor->id,
        'total' => 10000,
    ]);
    AnticipoAplicacion::factory()->create([
        'factura_id' => $factura->id,
        'monto' => 7000,
    ]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/notas-credito', [
            'factura_id' => $factura->id,
            'monto' => 4000, // 7000 + 4000 = 11000 > 10000
            'concepto' => 'No cabe',
            'fecha_emision' => '2026-04-26',
        ])
        ->assertSessionHasErrors(['monto']);
});

test('XML CFDI auto-llena uuid_fiscal, totales e impuestos detalle', function () {
    Storage::fake('public');
    $factura = Factura::factory()->create([
        'proveedor_id' => $this->proveedor->id,
        'total' => 50000,
    ]);

    $xmlContent = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital"
    Version="4.0" Fecha="2026-04-20T10:00:00" Folio="NC100" SubTotal="2000.00" Total="2320.00" Moneda="MXN">
  <cfdi:Emisor Rfc="EME000101AAA" Nombre="Emisor" RegimenFiscal="601"/>
  <cfdi:Receptor Rfc="REC000101BBB" Nombre="Receptor" UsoCFDI="G03"/>
  <cfdi:Impuestos TotalImpuestosTrasladados="320.00">
    <cfdi:Traslados>
      <cfdi:Traslado Base="2000.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="320.00"/>
    </cfdi:Traslados>
  </cfdi:Impuestos>
  <cfdi:Complemento>
    <tfd:TimbreFiscalDigital UUID="ABCD1234-NCNC-NCNC-NCNC-NCNCABCD1234" FechaTimbrado="2026-04-20T10:01:00"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/notas-credito', [
            'factura_id' => $factura->id,
            'monto' => 99,
            'concepto' => 'Devolución parcial',
            'fecha_emision' => '2026-04-20',
            'xml' => UploadedFile::fake()->createWithContent('nc.xml', $xmlContent),
        ])
        ->assertRedirect();

    $nota = NotaCredito::first();
    expect($nota->uuid_fiscal)->toBe('ABCD1234-NCNC-NCNC-NCNC-NCNCABCD1234');
    expect($nota->folio_fiscal)->toBe('NC100');
    expect((float) $nota->monto)->toBe(2320.0);
    expect((float) $nota->subtotal)->toBe(2000.0);
    expect((float) $nota->iva_trasladado)->toBe(320.0);
    expect($nota->impuestos_detalle)->toBeArray();

    $this->assertDatabaseHas('media', [
        'mediable_type' => NotaCredito::class,
        'descripcion' => DocumentoTipo::XmlNotaCredito->value,
    ]);
});

test('rechaza XML con UUID ya registrado', function () {
    NotaCredito::factory()->create([
        'uuid_fiscal' => 'DUPL1234-NCNC-NCNC-NCNC-NCNCDUPL1234',
    ]);
    $factura = Factura::factory()->create([
        'proveedor_id' => $this->proveedor->id,
        'total' => 50000,
    ]);

    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital"
    Version="4.0" Fecha="2026-04-20T10:00:00" Folio="NC101" SubTotal="100" Total="100" Moneda="MXN">
  <cfdi:Emisor Rfc="EME000101AAA" Nombre="A" RegimenFiscal="601"/>
  <cfdi:Receptor Rfc="REC000101BBB" Nombre="B" UsoCFDI="G03"/>
  <cfdi:Complemento>
    <tfd:TimbreFiscalDigital UUID="DUPL1234-NCNC-NCNC-NCNC-NCNCDUPL1234" FechaTimbrado="2026-04-20T10:01:00"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/notas-credito', [
            'factura_id' => $factura->id,
            'monto' => 100,
            'concepto' => 'Dup',
            'fecha_emision' => '2026-04-20',
            'xml' => UploadedFile::fake()->createWithContent('nc.xml', $xml),
        ])
        ->assertSessionHasErrors(['xml']);
});

test('no permite registrar nota sobre factura cancelada', function () {
    $factura = Factura::factory()->create([
        'proveedor_id' => $this->proveedor->id,
        'estatus' => 'cancelada',
        'total' => 5000,
    ]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/notas-credito', [
            'factura_id' => $factura->id,
            'monto' => 1000,
            'concepto' => 'No deberia registrarse',
            'fecha_emision' => '2026-04-26',
        ])
        ->assertSessionHasErrors(['factura_id']);
});
