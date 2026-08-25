<?php

use App\Enums\Costos\FacturaEstatus;
use App\Models\Alm\Almacen;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Producto;
use App\Models\Media;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * La recepción contra orden se captura en Almacén: es ahí donde se dice a qué
 * almacén entra el material, que es lo que mueve el kardex. Las reglas de
 * Costos (tope contra lo pedido y avance de la factura) viajan con ella.
 */
beforeEach(function () {
    Permission::firstOrCreate(['name' => 'alm.entradas.crear', 'guard_name' => 'web']);

    $this->almacenista = User::factory()->create();
    $this->almacenista->givePermissionTo('alm.entradas.crear');

    $this->almacen = Almacen::factory()->create(['responsable_id' => $this->almacenista->id]);

    $this->producto = Producto::factory()->create(['controla_inventario' => true]);
    $this->orden = OrdenCompra::factory()->pendienteEntrega()->create(['moneda' => 'mxn']);
    $this->partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $this->orden->id,
        'producto_id' => $this->producto->id,
        'cantidad' => 100,
        'precio_unitario' => 45,
        'subtotal' => 4500,
    ]);
});

function recibir(array $payload = []): array
{
    return array_replace([
        'orden_compra_id' => test()->orden->id,
        'almacen_id' => test()->almacen->id,
        'fecha_entrega' => now()->toDateString(),
        'tipo' => 'parcial',
        'detalles' => [
            ['orden_compra_detalle_id' => test()->partida->id, 'cantidad_recibida' => 60],
        ],
    ], $payload);
}

test('el almacenista recibe contra la orden y el material entra al kardex', function () {
    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', recibir())
        ->assertRedirect();

    $entrada = Entrega::firstOrFail();

    expect($entrada->orden_compra_id)->toBe($this->orden->id)
        ->and($entrada->almacen_id)->toBe($this->almacen->id)
        // El renglón sella el artículo de la partida para que el kardex no
        // cambie de artículo si alguien la re-apunta.
        ->and($entrada->detalles->first()->producto_id)->toBe($this->producto->id);

    $existencia = Existencia::firstOrFail();

    expect((float) $existencia->cantidad)->toBe(60.0)
        ->and((float) $existencia->costo_promedio)->toBe(45.0)
        ->and(Movimiento::where('documento_id', $entrada->id)->count())->toBe(1);
});

test('sin permiso de almacén no se puede recibir', function () {
    $intruso = User::factory()->create();

    $this->actingAs($intruso)
        ->post('/admin/almacen/entradas', recibir())
        ->assertForbidden();

    expect(Entrega::count())->toBe(0);
});

test('la entrada exige decir a qué almacén entra', function () {
    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', recibir(['almacen_id' => null]))
        ->assertSessionHasErrors('almacen_id');

    expect(Entrega::count())->toBe(0);
});

test('no se puede recibir más de lo que falta de la partida', function () {
    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', recibir())
        ->assertRedirect();

    // Ya entraron 60 de 100: 50 más se pasan del saldo.
    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', recibir([
            'detalles' => [
                ['orden_compra_detalle_id' => $this->partida->id, 'cantidad_recibida' => 50],
            ],
        ]))
        ->assertSessionHasErrors('detalles.0.cantidad_recibida');

    expect(Entrega::count())->toBe(1);
});

test('la partida de otra orden se rechaza', function () {
    $ajena = OrdenCompraDetalle::factory()->create(['cantidad' => 10]);

    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', recibir([
            'detalles' => [
                ['orden_compra_detalle_id' => $ajena->id, 'cantidad_recibida' => 1],
            ],
        ]))
        ->assertSessionHasErrors('detalles.0.orden_compra_detalle_id');
});

test('la entrada que completa la factura la manda a aprobación cuando ya hay comprobante', function () {
    $factura = Factura::factory()->create([
        'orden_compra_id' => $this->orden->id,
        'proveedor_id' => $this->orden->proveedor_id,
        'estatus' => FacturaEstatus::PendienteRecepcion->value,
    ]);

    // El comprobante de recepción es el otro requisito para que avance.
    Media::create([
        'mediable_type' => Factura::class,
        'mediable_id' => $factura->id,
        'descripcion' => 'comprobante_recepcion',
        'nombre_original' => 'comprobante.pdf',
        'path' => 'costos/comprobantes/x.pdf',
        'mime' => 'application/pdf',
        'size' => 10,
    ]);

    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', recibir([
            'tipo' => 'completa',
            'factura_id' => $factura->id,
            'completa_factura' => true,
            'detalles' => [
                ['orden_compra_detalle_id' => $this->partida->id, 'cantidad_recibida' => 100],
            ],
        ]))
        ->assertRedirect();

    $factura->refresh();

    expect($factura->completamente_entregada)->toBeTrue()
        ->and($factura->estatus)->toBe(FacturaEstatus::PendienteAprobacion);
});

test('una factura de otra orden no se puede ligar', function () {
    $ajena = Factura::factory()->create(['estatus' => FacturaEstatus::PendienteRecepcion->value]);

    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', recibir(['factura_id' => $ajena->id]))
        ->assertSessionHasErrors('factura_id');

    expect(Entrega::count())->toBe(0);
});

test('el precio real distinto al de la orden queda en el renglón y en el kardex', function () {
    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', recibir([
            'detalles' => [
                ['orden_compra_detalle_id' => $this->partida->id, 'cantidad_recibida' => 60, 'precio_unitario' => 47.25],
            ],
        ]))
        ->assertRedirect();

    expect((float) Entrega::firstOrFail()->detalles->first()->precio_unitario)->toBe(47.25)
        ->and((float) Existencia::firstOrFail()->costo_promedio)->toBe(47.25);
});

test('la pantalla de captura baja las partidas de la orden con su saldo', function () {
    $this->actingAs($this->almacenista)
        ->post('/admin/almacen/entradas', recibir())
        ->assertRedirect();

    $this->actingAs($this->almacenista)
        ->get("/admin/almacen/entradas/create?orden_compra_id={$this->orden->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('orden.folio', $this->orden->folio)
            ->where('orden.partidas.0.recibido', 60)
            ->where('orden.partidas.0.pendiente', 40)
            ->where('orden.partidas.0.mueve_kardex', true)
        );
});
