<?php

use App\Enums\Costos\FacturaEstatus;
use App\Models\Alm\Almacen;
use App\Models\Alm\Movimiento;
use App\Models\Costos\ConfiguracionCostos;
use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\Costos\CfdiXmlParser;
use App\Services\Costos\RegistradorFacturaCfdi;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

/**
 * El proveedor cotizó en una moneda y timbra en otra.
 *
 * La factura se guarda en la moneda de la orden —contra ella se miden saldo,
 * porcentajes y pagos— y conserva lo que dijo el CFDI. El kardex, que vive en
 * pesos, costea con el tipo de cambio de esa factura.
 */
beforeEach(function () {
    Storage::fake('public');

    Permission::firstOrCreate(['name' => 'alm.entradas.crear', 'guard_name' => 'web']);

    $this->almacenista = User::factory()->create();
    $this->almacenista->givePermissionTo('alm.entradas.crear');

    $this->almacen = Almacen::factory()->create(['responsable_id' => $this->almacenista->id]);
    $this->producto = Producto::factory()->create();

    $this->ordenEn = function (string $moneda, float $tipoCambio = 1): OrdenCompra {
        $orden = OrdenCompra::factory()->pendienteEntrega()->create([
            'proveedor_id' => Proveedor::factory(),
            'moneda' => $moneda,
            'tipo_cambio' => $tipoCambio,
            'total' => 5220,
        ]);

        // 100 × 45 = 4,500 + 16% = 5,220 en la moneda de la orden.
        $this->partida = OrdenCompraDetalle::factory()->create([
            'orden_compra_id' => $orden->id,
            'producto_id' => $this->producto->id,
            'cantidad' => 100,
            'precio_unitario' => 45,
            'subtotal' => 4500,
        ]);

        return $orden;
    };

    $this->recibir = function (OrdenCompra $orden, array $xml) {
        $detalles = [['orden_compra_detalle_id' => $this->partida->id, 'cantidad_recibida' => 60]];

        return $this->actingAs($this->almacenista)->post('/admin/almacen/entradas', [
            'orden_compra_id' => $orden->id,
            'almacen_id' => $this->almacen->id,
            'fecha_entrega' => now()->toDateString(),
            'tipo' => 'parcial',
            'detalles' => $detalles,
            ...cfdiParaRecibir($orden, $detalles, null, $xml),
        ]);
    };
});

test('el parser lee la moneda y el tipo de cambio del CFDI', function () {
    $parser = new CfdiXmlParser;

    expect($parser->parse(cfdiXml(['Moneda' => 'USD', 'TipoCambio' => '18.7']))['moneda'])->toBe('usd')
        ->and($parser->parse(cfdiXml(['Moneda' => 'USD', 'TipoCambio' => '18.7']))['tipo_cambio'])->toBe(18.7)
        ->and($parser->parse(cfdiXml(['Moneda' => 'MXN', 'TipoCambio' => '1']))['tipo_cambio'])->toBeNull();
});

test('orden en dolares facturada en pesos: el tipo de cambio sale de la factura', function () {
    $orden = ($this->ordenEn)('usd', 18.30);

    // Entran 60 × 45 = 2,700 USD + IVA = 3,132 USD. El proveedor facturó a 18.50.
    ($this->recibir)($orden, [
        'Moneda' => 'MXN',
        'SubTotal' => '49950.00',
        'IvaTrasladado' => '7992.00',
        'Total' => '57942.00',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $factura = Factura::sole();

    expect($factura->moneda)->toBe('usd')
        ->and((float) $factura->total)->toBe(3132.0)
        ->and((float) $factura->subtotal)->toBe(2700.0)
        ->and($factura->moneda_cfdi)->toBe('mxn')
        ->and((float) $factura->total_cfdi)->toBe(57942.0)
        ->and((float) $factura->tipo_cambio_cfdi)->toBe(18.5)
        // El de la orden se queda: es la referencia contra la que se cargó el presupuesto.
        ->and((float) $factura->tipo_cambio)->toBe(18.3)
        // Y el kardex, en pesos: 45 USD × 18.50.
        ->and((float) Movimiento::sole()->costo_unitario)->toBe(832.5)
        ->and(Entrega::sole()->factura_id)->toBe($factura->id);
});

test('orden en dolares facturada en dolares: el kardex costea con el TipoCambio del XML', function () {
    $orden = ($this->ordenEn)('usd', 18.30);

    ($this->recibir)($orden, ['Moneda' => 'USD', 'TipoCambio' => '18.70'])
        ->assertSessionHasNoErrors();

    $factura = Factura::sole();

    expect($factura->moneda_cfdi)->toBe('usd')
        ->and((float) $factura->total)->toBe(3132.0)
        ->and((float) $factura->tipo_cambio_cfdi)->toBe(18.7)
        ->and((float) Movimiento::sole()->costo_unitario)->toBe(841.5);
});

test('orden en pesos facturada en dolares: los importes se convierten con el TipoCambio del XML', function () {
    $orden = ($this->ordenEn)('mxn');

    // 3,132 MXN esperados = 156.60 USD a 20.
    ($this->recibir)($orden, [
        'Moneda' => 'USD',
        'TipoCambio' => '20',
        'SubTotal' => '135.00',
        'IvaTrasladado' => '21.60',
        'Total' => '156.60',
    ])->assertSessionHasNoErrors();

    $factura = Factura::sole();

    expect($factura->moneda)->toBe('mxn')
        ->and((float) $factura->total)->toBe(3132.0)
        ->and($factura->moneda_cfdi)->toBe('usd')
        ->and((float) $factura->total_cfdi)->toBe(156.6)
        ->and((float) Movimiento::sole()->costo_unitario)->toBe(45.0);
});

test('orden en pesos facturada en dolares que no cuadra con lo recibido se rechaza', function () {
    $orden = ($this->ordenEn)('mxn');

    ($this->recibir)($orden, [
        'Moneda' => 'USD',
        'TipoCambio' => '20',
        'SubTotal' => '150.00',
        'IvaTrasladado' => '24.00',
        'Total' => '174.00',
    ])->assertSessionHasErrors('xml');

    expect(Factura::count())->toBe(0)
        ->and(Entrega::count())->toBe(0);
});

test('un CFDI en divisa sin TipoCambio no se puede convertir', function () {
    $orden = ($this->ordenEn)('mxn');

    ($this->recibir)($orden, ['Moneda' => 'USD'])->assertSessionHasErrors('xml');

    expect(Factura::count())->toBe(0);
});

test('un CFDI en pesos cuyo tipo de cambio se aleja del FIX se rechaza: no es de esta orden', function () {
    $orden = ($this->ordenEn)('usd', 18.30);

    // El proveedor facturó las 100 piezas (5,220 USD a 18.50 = 96,570) y hoy
    // entran 60: el cociente daría 30.83 contra un FIX de 18.50.
    ($this->recibir)($orden, [
        'Moneda' => 'MXN',
        'SubTotal' => '83250.00',
        'IvaTrasladado' => '13320.00',
        'Total' => '96570.00',
    ])->assertSessionHasErrors('xml');

    expect(Factura::count())->toBe(0)
        ->and(Entrega::count())->toBe(0);
});

test('la tolerancia del tipo de cambio es configurable', function () {
    $orden = ($this->ordenEn)('usd', 18.30);

    // A 19.50 el desvío contra el FIX de 18.50 es 5.4%.
    $xml = ['Moneda' => 'MXN', 'SubTotal' => '52650.00', 'IvaTrasladado' => '8424.00', 'Total' => '61074.00'];

    ($this->recibir)($orden, $xml)->assertSessionHasErrors('xml');

    ConfiguracionCostos::actual()->update(['tolerancia_tipo_cambio' => 6]);

    ($this->recibir)($orden, $xml)->assertSessionHasNoErrors();

    expect((float) Factura::sole()->tipo_cambio_cfdi)->toBe(19.5);
});

test('fuera de almacen, una factura en pesos de una orden en dolares se mide contra el saldo por facturar', function () {
    $orden = ($this->ordenEn)('usd', 18.30);
    $registrador = app(RegistradorFacturaCfdi::class);

    // Una factura por orden: 5,220 USD a 18.50.
    $fiscal = app(CfdiXmlParser::class)->parse(cfdiXml([
        'Moneda' => 'MXN',
        'SubTotal' => '83250.00',
        'IvaTrasladado' => '13320.00',
        'Total' => '96570.00',
    ]));

    expect($registrador->validar($orden, $fiscal))->toBeNull();

    $factura = $registrador->registrar($orden, $fiscal, ['estatus' => FacturaEstatus::PendienteRecepcion]);

    expect((float) $factura->total)->toBe(5220.0)
        ->and($factura->moneda)->toBe('usd')
        ->and((float) $factura->total_cfdi)->toBe(96570.0)
        ->and((float) $factura->tipo_cambio_cfdi)->toBe(18.5);
});

test('fuera de almacen, una factura en pesos que no cubre la orden en dolares se rechaza por el tipo de cambio', function () {
    $orden = ($this->ordenEn)('usd', 18.30);

    // Sólo 60 piezas facturadas contra el saldo completo: cociente de 11.1.
    $error = app(RegistradorFacturaCfdi::class)->validar(
        $orden,
        app(CfdiXmlParser::class)->parse(cfdiXml(['Moneda' => 'MXN', 'Total' => '57942.00'])),
    );

    expect($error)->toContain('FIX de Banxico');
});

test('fuera de almacen, una factura en dolares de una orden en pesos se registra convertida', function () {
    $orden = ($this->ordenEn)('mxn');
    $registrador = app(RegistradorFacturaCfdi::class);
    $fiscal = app(CfdiXmlParser::class)->parse(cfdiXml([
        'Moneda' => 'USD',
        'TipoCambio' => '20',
        'SubTotal' => '225.00',
        'IvaTrasladado' => '36.00',
        'Total' => '261.00',
    ]));

    expect($registrador->validar($orden, $fiscal))->toBeNull();

    $factura = $registrador->registrar($orden, $fiscal, ['estatus' => FacturaEstatus::PendienteRecepcion]);

    expect((float) $factura->total)->toBe(5220.0)
        ->and($factura->moneda_cfdi)->toBe('usd')
        ->and((float) $factura->total_cfdi)->toBe(261.0);
});

test('una factura en dolares que excede el saldo de una orden en pesos se rechaza ya convertida', function () {
    $orden = ($this->ordenEn)('mxn');

    // 300 USD × 20 = 6,000 MXN contra un saldo de 5,220.
    $error = app(RegistradorFacturaCfdi::class)->validar(
        $orden,
        app(CfdiXmlParser::class)->parse(cfdiXml(['Moneda' => 'USD', 'TipoCambio' => '20', 'Total' => '300.00'])),
    );

    expect($error)->toContain('excede el saldo facturable');
});
