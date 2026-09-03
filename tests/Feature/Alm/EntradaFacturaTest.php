<?php

use App\Enums\Costos\FacturaEstatus;
use App\Models\Alm\Almacen;
use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

/**
 * El respaldo fiscal de la recepción contra orden.
 *
 * Lo normal es que la factura no exista cuando el material llega: el proveedor
 * rara vez la sube al portal y el papel viaja con el camión. Por eso el CFDI se
 * adjunta al recibir, la factura nace ahí, y su total se contrasta contra lo que
 * está entrando —que es lo único que atrapa el XML de otra entrega.
 */
beforeEach(function () {
    Storage::fake('public');

    Permission::firstOrCreate(['name' => 'alm.entradas.crear', 'guard_name' => 'web']);

    $this->almacenista = User::factory()->create();
    $this->almacenista->givePermissionTo('alm.entradas.crear');

    $this->almacen = Almacen::factory()->create(['responsable_id' => $this->almacenista->id]);
    $this->producto = Producto::factory()->create(['controla_inventario' => true]);

    // 100 × 45 = 4,500 + 16% = 5,220. El total con impuestos es el que da el
    // saldo facturable de la orden.
    $this->orden = OrdenCompra::factory()->pendienteEntrega()->create([
        'proveedor_id' => Proveedor::factory(),
        'moneda' => 'mxn',
        'total' => 5220,
    ]);

    $this->partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $this->orden->id,
        'producto_id' => $this->producto->id,
        'cantidad' => 100,
        'precio_unitario' => 45,
        'subtotal' => 4500,
    ]);

    $this->recibe60 = [['orden_compra_detalle_id' => $this->partida->id, 'cantidad_recibida' => 60]];

    $this->capturar = fn (array $payload = []): array => array_replace([
        'orden_compra_id' => $this->orden->id,
        'almacen_id' => $this->almacen->id,
        'fecha_entrega' => now()->toDateString(),
        'tipo' => 'parcial',
        'detalles' => $this->recibe60,
    ], $payload);
});

test('sin XML la recepción contra orden no pasa', function () {
    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', ($this->capturar)([
            'pdf' => UploadedFile::fake()->create('factura.pdf', 20, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('xml');

    expect(Entrega::count())->toBe(0);
});

test('sin el PDF de la factura la recepción contra orden no pasa', function () {
    $archivos = cfdiParaRecibir($this->orden, $this->recibe60);

    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', ($this->capturar)(['xml' => $archivos['xml']]))
        ->assertSessionHasErrors('pdf');

    expect(Entrega::count())->toBe(0);
});

test('el CFDI da de alta la factura y la deja ligada a la entrada con sus archivos', function () {
    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', ($this->capturar)(cfdiParaRecibir($this->orden, $this->recibe60)))
        ->assertRedirect();

    $factura = Factura::sole();

    expect($factura->orden_compra_id)->toBe($this->orden->id)
        ->and($factura->estatus)->toBe(FacturaEstatus::PendienteRecepcion)
        // 60 × 45 = 2,700 + 16%.
        ->and((float) $factura->total)->toBe(3132.0)
        ->and(Entrega::sole()->factura_id)->toBe($factura->id)
        ->and($factura->mediaXml)->not->toBeNull()
        ->and($factura->mediaPdf)->not->toBeNull();

    Storage::disk('public')->assertExists($factura->mediaXml->path);
    Storage::disk('public')->assertExists($factura->mediaPdf->path);
});

test('un CFDI que no cuadra con lo recibido se rechaza y no deja factura a medias', function () {
    // El proveedor facturó las 100 piezas aunque sólo entregó 60.
    $archivos = cfdiParaRecibir($this->orden, $this->recibe60, null, [
        'SubTotal' => '4500.00',
        'Total' => '5220.00',
        'IvaTrasladado' => '720.00',
    ]);

    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', ($this->capturar)($archivos))
        ->assertSessionHasErrors('xml');

    expect(Entrega::count())->toBe(0)
        ->and(Factura::count())->toBe(0);
});

test('un CFDI con retención cuadra aunque su total venga reducido', function () {
    // El flete de una persona física retiene 4% de ISR: el total del CFDI baja,
    // y aun así ampara lo mismo.
    $this->orden->proveedor->update(['tipo_persona' => 'fisica']);
    $this->partida->update(['tipo_fiscal' => 'flete']);

    $orden = $this->orden->fresh();
    $importes = importesDeRecepcion($orden, $this->recibe60);

    expect($importes['isr_retenido'])->toBeGreaterThan(0)
        ->and($importes['total'])->toBeLessThan($importes['subtotal'] + $importes['iva']);

    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', ($this->capturar)(cfdiParaRecibir($orden, $this->recibe60)))
        ->assertRedirect();

    expect((float) Factura::sole()->total)->toBe($importes['total']);
});

test('la segunda parcial con el mismo CFDI liga la factura en vez de duplicarla', function () {
    $uuid = 'ABCD1234-ABCD-ABCD-ABCD-ABCDABCDABCD';

    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', ($this->capturar)(
            cfdiParaRecibir($this->orden, $this->recibe60, $uuid),
        ))
        ->assertRedirect();

    // La segunda trae las 40 restantes con el mismo papel: el CFDI amparaba las
    // dos entregas, y no hay una factura nueva que dar de alta.
    $resto = [['orden_compra_detalle_id' => $this->partida->id, 'cantidad_recibida' => 40]];

    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', ($this->capturar)([
            'tipo' => 'completa',
            'detalles' => $resto,
            ...cfdiParaRecibir($this->orden, $resto, $uuid),
        ]))
        ->assertRedirect();

    $factura = Factura::sole();

    expect(Entrega::count())->toBe(2)
        ->and($factura->media()->where('descripcion', 'xml_factura')->count())->toBe(1)
        ->and($factura->media()->where('descripcion', 'pdf_factura')->count())->toBe(1);
});

test('el CFDI que ya está registrado en otra orden se rechaza', function () {
    $ajena = OrdenCompra::factory()->pendienteEntrega()->create(['total' => 5220]);
    Factura::factory()->create([
        'orden_compra_id' => $ajena->id,
        'uuid_fiscal' => 'EEEE1111-EEEE-EEEE-EEEE-EEEEEEEEEEEE',
        'estatus' => FacturaEstatus::PendienteRecepcion->value,
    ]);

    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', ($this->capturar)(
            cfdiParaRecibir($this->orden, $this->recibe60, 'EEEE1111-EEEE-EEEE-EEEE-EEEEEEEEEEEE'),
        ))
        ->assertSessionHasErrors('xml');

    expect(Entrega::count())->toBe(0);
});

test('un XML ilegible se rechaza diciendo que no se pudo leer', function () {
    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', ($this->capturar)([
            'xml' => UploadedFile::fake()->createWithContent('factura.xml', 'esto no es un CFDI'),
            'pdf' => UploadedFile::fake()->create('factura.pdf', 20, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('xml');

    expect(Entrega::count())->toBe(0);
});

test('la factura que ya subió el proveedor se elige sin adjuntar archivos', function () {
    $factura = facturaQueAmpara(
        Factura::factory()->create([
            'orden_compra_id' => $this->orden->id,
            'proveedor_id' => $this->orden->proveedor_id,
            'estatus' => FacturaEstatus::PendienteRecepcion->value,
        ]),
        $this->orden,
        $this->recibe60,
    );

    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', ($this->capturar)(['factura_id' => $factura->id]))
        ->assertRedirect();

    expect(Entrega::sole()->factura_id)->toBe($factura->id)
        ->and(Factura::count())->toBe(1);
});

test('la factura elegida que no ampara lo recibido se rechaza', function () {
    $factura = Factura::factory()->create([
        'orden_compra_id' => $this->orden->id,
        'proveedor_id' => $this->orden->proveedor_id,
        'estatus' => FacturaEstatus::PendienteRecepcion->value,
        // Ampara la orden entera, y sólo van a entrar 60 de 100.
        'total' => 5220,
    ]);

    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', ($this->capturar)(['factura_id' => $factura->id]))
        ->assertSessionHasErrors('factura_id');

    expect(Entrega::count())->toBe(0);
});

test('la entrada sin orden sigue sin pedir factura', function () {
    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', [
            'almacen_id' => $this->almacen->id,
            'fecha_entrega' => now()->toDateString(),
            'detalles' => [
                ['articulo_id' => (int) app(\App\Services\Alm\ResolvedorArticulo::class)->paraProducto($this->producto->id), 'cantidad_recibida' => 5, 'precio_unitario' => 30],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Entrega::sole()->orden_compra_id)->toBeNull()
        ->and(Factura::count())->toBe(0);
});

/**
 * El comprobante de recepción: el mismo documento que antes se sacaba desde la
 * pestaña de recepciones de Costos, ahora alcanzable desde la entrada.
 *
 * Se arma con lo de la orden —proveedor, partidas, retenciones de la factura—
 * así que una entrada sin orden no tiene con qué armarlo y no lo ofrece.
 */
test('la recepción con orden puede imprimir su comprobante', function () {
    $archivos = cfdiParaRecibir($this->orden, $this->recibe60);

    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', ($this->capturar)($archivos))
        ->assertRedirect();

    $entrada = Entrega::sole();

    $this->actingAs($this->almacenista)
        ->get(route('admin.costos.entregas.pdf', $entrada))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
