<?php

use App\Enums\Costos\ComplementoPagoEstatus;
use App\Mail\ComplementoPendienteMail;
use App\Models\Costos\ComplementoPago;
use App\Models\Costos\Factura;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Pago;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\Costos\CfdiXmlParser;
use App\Services\Costos\ComplementoPagoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function cfdiFactura(string $metodoPago = 'PPD', string $uuid = 'FAC00001-0000-0000-0000-000000000001'): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital"
    Version="4.0" Fecha="2026-04-24T10:00:00" Folio="A1" SubTotal="1000.00" Total="1160.00" Moneda="MXN"
    MetodoPago="{$metodoPago}" FormaPago="99" TipoDeComprobante="I">
  <cfdi:Emisor Rfc="EME000101AAA" Nombre="Emisor SA" RegimenFiscal="601"/>
  <cfdi:Receptor Rfc="REC000101BBB" Nombre="Receptor SA" UsoCFDI="G03"/>
  <cfdi:Complemento>
    <tfd:TimbreFiscalDigital UUID="{$uuid}" FechaTimbrado="2026-04-24T10:01:00"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;
}

function cfdiComplementoPago(string $facturaUuid, float $impPagado, string $uuid = 'CP000001-0000-0000-0000-000000000001'): string
{
    $imp = number_format($impPagado, 2, '.', '');

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital"
    xmlns:pago20="http://www.sat.gob.mx/Pagos20"
    Version="4.0" Fecha="2026-05-02T10:00:00" SubTotal="0" Total="0" Moneda="XXX" TipoDeComprobante="P">
  <cfdi:Emisor Rfc="EME000101AAA" Nombre="Emisor SA" RegimenFiscal="601"/>
  <cfdi:Receptor Rfc="REC000101BBB" Nombre="Receptor SA" UsoCFDI="CP01"/>
  <cfdi:Complemento>
    <pago20:Pagos Version="2.0">
      <pago20:Pago FechaPago="2026-05-01T12:00:00" Monto="{$imp}" MonedaP="MXN">
        <pago20:DoctoRelacionado IdDocumento="{$facturaUuid}" ImpPagado="{$imp}"/>
      </pago20:Pago>
    </pago20:Pagos>
    <tfd:TimbreFiscalDigital UUID="{$uuid}" FechaTimbrado="2026-05-02T10:01:00"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;
}

/**
 * REP que referencia varias facturas, como el que emite un proveedor cuando una
 * sola transferencia liquida varios documentos.
 *
 * @param  array<string, float>  $docs  uuid de factura => importe pagado
 */
function cfdiComplementoMultiple(array $docs, string $uuid = 'CP000001-0000-0000-0000-000000000009'): string
{
    $relacionados = collect($docs)
        ->map(fn (float $imp, string $facturaUuid) => sprintf(
            '<pago20:DoctoRelacionado IdDocumento="%s" ImpPagado="%s"/>',
            $facturaUuid,
            number_format($imp, 2, '.', ''),
        ))
        ->implode("\n        ");

    $total = number_format(array_sum($docs), 2, '.', '');

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital"
    xmlns:pago20="http://www.sat.gob.mx/Pagos20"
    Version="4.0" Fecha="2026-05-02T10:00:00" SubTotal="0" Total="0" Moneda="XXX" TipoDeComprobante="P">
  <cfdi:Emisor Rfc="EME000101AAA" Nombre="Emisor SA" RegimenFiscal="601"/>
  <cfdi:Receptor Rfc="REC000101BBB" Nombre="Receptor SA" UsoCFDI="CP01"/>
  <cfdi:Complemento>
    <pago20:Pagos Version="2.0">
      <pago20:Pago FechaPago="2026-05-01T12:00:00" Monto="{$total}" MonedaP="MXN">
        {$relacionados}
      </pago20:Pago>
    </pago20:Pagos>
    <tfd:TimbreFiscalDigital UUID="{$uuid}" FechaTimbrado="2026-05-02T10:01:00"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;
}

describe('parser', function () {
    test('lee MetodoPago PPD y PUE', function () {
        $parser = new CfdiXmlParser;
        expect($parser->parse(cfdiFactura('PPD'))['metodo_pago'])->toBe('PPD');
        expect($parser->parse(cfdiFactura('PUE'))['metodo_pago'])->toBe('PUE');
        expect($parser->parse(cfdiFactura('PPD'))['tipo_comprobante'])->toBe('I');
    });

    test('parsea complemento de pago (tipo P)', function () {
        $parser = new CfdiXmlParser;
        $r = $parser->parseComplementoPago(cfdiComplementoPago('FAC00001-0000-0000-0000-000000000001', 1160.00));

        expect($r['tipo_comprobante'])->toBe('P');
        expect($r['fecha_pago'])->toBe('2026-05-01');
        expect($r['docs_relacionados'])->toHaveCount(1);
        expect($r['docs_relacionados'][0]['uuid'])->toBe('FAC00001-0000-0000-0000-000000000001');
        expect($r['docs_relacionados'][0]['imp_pagado'])->toBe(1160.0);
    });
});

describe('obligacion', function () {
    function pagoDeFactura(string $metodoPago, float $monto = 1160): Pago
    {
        $factura = Factura::factory()->create([
            'metodo_pago' => $metodoPago,
            'total' => $monto,
            'uuid_fiscal' => 'FAC00001-0000-0000-0000-000000000001',
        ]);

        return Pago::factory()->pagado()->create([
            'pagable_type' => Factura::class,
            'pagable_id' => $factura->id,
            'monto_pago' => $monto,
        ]);
    }

    test('genera obligacion para pago de factura PPD con fecha limite dia 5 mes siguiente', function () {
        Carbon::setTestNow('2026-05-15 10:00:00');
        $pago = pagoDeFactura('PPD');

        $obligacion = app(ComplementoPagoService::class)->generarObligacion($pago);

        expect($obligacion)->not->toBeNull();
        expect($obligacion->estatus)->toBe(ComplementoPagoEstatus::Pendiente);
        expect($obligacion->fecha_limite->format('Y-m-d'))->toBe('2026-06-05');
        Carbon::setTestNow();
    });

    test('factura PUE no genera obligacion', function () {
        $pago = pagoDeFactura('PUE');
        expect(app(ComplementoPagoService::class)->generarObligacion($pago))->toBeNull();
    });

    test('es idempotente por pago', function () {
        $pago = pagoDeFactura('PPD');
        $service = app(ComplementoPagoService::class);
        $service->generarObligacion($pago);
        expect($service->generarObligacion($pago->fresh()))->toBeNull();
        expect(ComplementoPago::where('pago_id', $pago->id)->count())->toBe(1);
    });

    test('uploadComprobante de factura PPD genera la obligacion', function () {
        Mail::fake();
        Notification::fake();
        Storage::fake('public');

        $factura = Factura::factory()->pendientePago()->create([
            'metodo_pago' => 'PPD',
            'total' => 1160,
            'proveedor_id' => Proveedor::factory()->create(['email' => 'ppd@test.com'])->id,
        ]);
        $pago = Pago::factory()->programado()->create([
            'pagable_type' => Factura::class,
            'pagable_id' => $factura->id,
            'monto_pago' => 1160,
        ]);

        $this->actingAs(User::factory()->create())
            ->post("/admin/costos/pagos/{$pago->id}/upload-comprobante", [
                'comprobante' => UploadedFile::fake()->create('comprobante.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect();

        expect(ComplementoPago::where('pago_id', $pago->id)->where('estatus', 'pendiente')->count())->toBe(1);
        Mail::assertSent(ComplementoPendienteMail::class);
    });
});

describe('bloqueo', function () {
    test('proveedor con obligacion pendiente queda bloqueado', function () {
        $prov = Proveedor::factory()->create();
        expect($prov->bloqueadoPorComplemento())->toBeFalse();

        ComplementoPago::factory()->pendiente()->create(['proveedor_id' => $prov->id]);
        expect($prov->fresh()->bloqueadoPorComplemento())->toBeTrue();
    });

    test('solicitud de pago rechaza proveedor bloqueado', function () {
        Permission::firstOrCreate(['name' => 'costos.solicitudes-pago.crear', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('costos.solicitudes-pago.crear');

        $depto = Departamento::factory()->create();
        $prov = Proveedor::factory()->create();
        ComplementoPago::factory()->pendiente()->create(['proveedor_id' => $prov->id]);

        $tipo = \App\Models\Costos\TipoSolicitud::factory()->create();

        $this->actingAs($user)
            ->post('/admin/costos/solicitudes-pago', [
                'departamento_id' => $depto->id,
                'proveedor_id' => $prov->id,
                'tipo_solicitud_id' => $tipo->id,
                'concepto' => 'Pago de prueba',
                'tipo_pago' => 'transferencia',
                'tipo_moneda' => 'mxn',
                'monto_total' => 100,
            ])
            ->assertSessionHasErrors('proveedor_id');
    });

    test('liberar requisicion aborta para proveedor bloqueado', function () {
        Permission::firstOrCreate(['name' => 'costos.requisiciones.liberar', 'guard_name' => 'web']);
        $compras = User::factory()->create();
        $compras->givePermissionTo('costos.requisiciones.liberar');

        $depto = Departamento::factory()->create();
        $rubro = ObraRubro::factory()->create();
        $req = Requisicion::factory()->aprobada()->create(['departamento_id' => $depto->id]);
        $detalle = RequisicionDetalle::factory()->create([
            'requisicion_id' => $req->id,
            'obra_rubro_id' => $rubro->id,
            'cantidad' => 10,
        ]);
        $proveedor = Proveedor::factory()->create();
        ComplementoPago::factory()->pendiente()->create(['proveedor_id' => $proveedor->id]);

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
            ->assertSessionHasErrors('ocs');

        expect(OrdenCompra::where('requisicion_id', $req->id)->count())->toBe(0);
    });
});

describe('portal complemento', function () {
    beforeEach(function () {
        Storage::fake('public');
        $this->proveedor = Proveedor::factory()->create([
            'tiene_acceso_portal' => true,
            'activo' => true,
        ]);
    });

    function obligacionParaPortal(
        Proveedor $prov,
        float $monto = 1160,
        string $uuidFactura = 'FAC00001-0000-0000-0000-000000000001',
    ): ComplementoPago {
        $factura = Factura::factory()->create([
            'proveedor_id' => $prov->id,
            'metodo_pago' => 'PPD',
            'uuid_fiscal' => $uuidFactura,
            'total' => $monto,
        ]);
        $pago = Pago::factory()->pagado()->create([
            'pagable_type' => Factura::class,
            'pagable_id' => $factura->id,
            'monto_pago' => $monto,
        ]);

        return ComplementoPago::factory()->pendiente()->create([
            'factura_id' => $factura->id,
            'pago_id' => $pago->id,
            'proveedor_id' => $prov->id,
            'monto_pago' => $monto,
        ]);
    }

    test('complemento valido marca cumplida la obligacion y libera el bloqueo', function () {
        $obligacion = obligacionParaPortal($this->proveedor, 1160);

        $xml = UploadedFile::fake()->createWithContent('cp.xml', cfdiComplementoPago('FAC00001-0000-0000-0000-000000000001', 1160.00));

        $this->actingAs($this->proveedor, 'proveedor')
            ->post('/portal/complementos', ['xml' => $xml])
            ->assertSessionHasNoErrors();

        expect($obligacion->fresh()->estatus)->toBe(ComplementoPagoEstatus::Cumplido);
        expect($this->proveedor->fresh()->bloqueadoPorComplemento())->toBeFalse();
    });

    test('rechaza el complemento que no toca ninguna factura del proveedor', function () {
        obligacionParaPortal($this->proveedor, 1160);

        $xml = UploadedFile::fake()->createWithContent('cp.xml', cfdiComplementoPago('DESCONOCI-0000-0000-0000-000000000000', 1160.00));

        $this->actingAs($this->proveedor, 'proveedor')
            ->post('/portal/complementos', ['xml' => $xml])
            ->assertSessionHasErrors('xml');
    });

    test('una parcialidad cubre parte del pago y deja pendiente la obligacion', function () {
        $obligacion = obligacionParaPortal($this->proveedor, 1000);

        $xml = UploadedFile::fake()->createWithContent('cp.xml', cfdiComplementoPago('FAC00001-0000-0000-0000-000000000001', 400.00));

        $this->actingAs($this->proveedor, 'proveedor')
            ->post('/portal/complementos', ['xml' => $xml])
            ->assertSessionHasNoErrors();

        $fresca = $obligacion->fresh();

        // Marcarla cumplida aqui liberaria al proveedor debiendo 600 de complemento.
        expect($fresca->estatus)->toBe(ComplementoPagoEstatus::Pendiente)
            ->and((float) $fresca->monto_cubierto)->toBe(400.0)
            ->and($fresca->saldoPorComplementar())->toBe(600.0)
            ->and($this->proveedor->fresh()->bloqueadoPorComplemento())->toBeTrue();
    });

    test('las parcialidades se acumulan hasta cumplir la obligacion', function () {
        $obligacion = obligacionParaPortal($this->proveedor, 1000);

        foreach ([['a', 400.00], ['b', 600.00]] as [$sufijo, $monto]) {
            $this->actingAs($this->proveedor, 'proveedor')->post('/portal/complementos', [
                'xml' => UploadedFile::fake()->createWithContent(
                    'cp.xml',
                    cfdiComplementoPago(
                        'FAC00001-0000-0000-0000-000000000001',
                        $monto,
                        "CP00000{$sufijo}-0000-0000-0000-000000000001",
                    ),
                ),
            ])->assertSessionHasNoErrors();
        }

        $fresca = $obligacion->fresh();

        expect($fresca->estatus)->toBe(ComplementoPagoEstatus::Cumplido)
            ->and((float) $fresca->monto_cubierto)->toBe(1000.0)
            ->and($fresca->recibidos()->count())->toBe(2)
            ->and($this->proveedor->fresh()->bloqueadoPorComplemento())->toBeFalse();
    });

    test('un REP para varias facturas cumple cada una por su UUID', function () {
        $uno = obligacionParaPortal($this->proveedor, 500, 'FAC00001-0000-0000-0000-000000000001');
        $dos = obligacionParaPortal($this->proveedor, 300, 'FAC00002-0000-0000-0000-000000000002');

        $xml = UploadedFile::fake()->createWithContent('cp.xml', cfdiComplementoMultiple([
            'FAC00001-0000-0000-0000-000000000001' => 500.00,
            'FAC00002-0000-0000-0000-000000000002' => 300.00,
        ]));

        $this->actingAs($this->proveedor, 'proveedor')
            ->post('/portal/complementos', ['xml' => $xml])
            ->assertSessionHasNoErrors();

        expect($uno->fresh()->estatus)->toBe(ComplementoPagoEstatus::Cumplido)
            ->and($dos->fresh()->estatus)->toBe(ComplementoPagoEstatus::Cumplido);
    });

    test('aplica las facturas que empatan y reporta la que no', function () {
        $conocida = obligacionParaPortal($this->proveedor, 500, 'FAC00001-0000-0000-0000-000000000001');

        $xml = UploadedFile::fake()->createWithContent('cp.xml', cfdiComplementoMultiple([
            'FAC00001-0000-0000-0000-000000000001' => 500.00,
            'DESCONOCI-0000-0000-0000-000000000000' => 300.00,
        ]));

        $respuesta = $this->actingAs($this->proveedor, 'proveedor')
            ->post('/portal/complementos', ['xml' => $xml]);

        // La buena entra aunque la otra falle.
        $respuesta->assertSessionHasNoErrors();
        expect($conocida->fresh()->estatus)->toBe(ComplementoPagoEstatus::Cumplido);

        $resultados = collect(session('resultado_complemento'));

        expect($resultados)->toHaveCount(2)
            ->and($resultados->where('aplicado', true))->toHaveCount(1)
            ->and($resultados->firstWhere('aplicado', false)['uuid'])
            ->toBe('DESCONOCI-0000-0000-0000-000000000000');
    });

    test('el mismo REP no se puede registrar dos veces', function () {
        obligacionParaPortal($this->proveedor, 1000);

        $subir = fn () => $this->actingAs($this->proveedor, 'proveedor')->post('/portal/complementos', [
            'xml' => UploadedFile::fake()->createWithContent(
                'cp.xml',
                cfdiComplementoPago('FAC00001-0000-0000-0000-000000000001', 400.00),
            ),
        ]);

        $subir()->assertSessionHasNoErrors();
        $subir()->assertSessionHasErrors('xml');
    });

    test('no aplica nada si la factura ya no tiene complementos pendientes', function () {
        $obligacion = obligacionParaPortal($this->proveedor, 500);
        $obligacion->update(['monto_cubierto' => 500]);
        $obligacion->transitionTo(ComplementoPagoEstatus::Cumplido);

        $this->actingAs($this->proveedor, 'proveedor')
            ->post('/portal/complementos', [
                'xml' => UploadedFile::fake()->createWithContent(
                    'cp.xml',
                    cfdiComplementoPago('FAC00001-0000-0000-0000-000000000001', 500.00, 'CP000002-0000-0000-0000-000000000002'),
                ),
            ])
            ->assertSessionHasErrors('xml');
    });

    test('rechaza XML que no es complemento de pago', function () {
        obligacionParaPortal($this->proveedor, 1160);
        $xml = UploadedFile::fake()->createWithContent('f.xml', cfdiFactura('PPD'));

        $this->actingAs($this->proveedor, 'proveedor')
            ->post('/portal/complementos', ['xml' => $xml])
            ->assertSessionHasErrors('xml');
    });
});

describe('comando', function () {
    test('marca vencidas las obligaciones cuyo plazo paso', function () {
        Carbon::setTestNow('2026-06-10 10:00:00');
        $obligacion = ComplementoPago::factory()->pendiente()->create([
            'fecha_limite' => '2026-06-05',
        ]);

        $this->artisan('costos:complementos-vencidos')->assertSuccessful();

        expect($obligacion->fresh()->estatus)->toBe(ComplementoPagoEstatus::Vencido);
        Carbon::setTestNow();
    });
});
