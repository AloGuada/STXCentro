<?php

use App\Exports\Costos\RecepcionesExport;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\Factura;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Pago;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Requisicion;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\Obra;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.ordenes-compra.ver', 'costos.ordenes-compra.ver-todas'] as $permiso) {
        Permission::firstOrCreate(['name' => $permiso]);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.ordenes-compra.ver', 'costos.ordenes-compra.ver-todas']);
});

test('lista las recepciones con su orden de compra', function () {
    $entrega = Entrega::factory()->create();

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/costos/recepciones/index')
            ->has('recepciones.data', 1)
            ->where('recepciones.data.0.id', $entrega->id)
            ->where('recepciones.data.0.oc.folio', $entrega->ordenCompra->folio)
        );
});

test('la recepción con factura ya pagada se lista y no se puede editar', function () {
    $oc = OrdenCompra::factory()->create();
    $factura = Factura::factory()->create(['orden_compra_id' => $oc->id]);
    Pago::factory()->create([
        'pagable_type' => Factura::class,
        'pagable_id' => $factura->id,
    ]);
    Entrega::factory()->create([
        'orden_compra_id' => $oc->id,
        'factura_id' => $factura->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('recepciones.data', 1)
            ->where('recepciones.data.0.factura.folio', $factura->folio)
            ->where('recepciones.data.0.puede_editar', false)
        );
});

test('la recepción de una OC de contado muestra su solicitud de pago ligada', function () {
    $oc = OrdenCompra::factory()->create(['tipo_pago' => 'contado']);
    $sp = SolicitudPago::factory()->create(['orden_compra_id' => $oc->id]);
    Entrega::factory()->create(['orden_compra_id' => $oc->id]);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recepciones.data', 1)
            ->has('recepciones.data.0.solicitudes_pago', 1)
            ->where('recepciones.data.0.solicitudes_pago.0.folio', $sp->folio)
        );
});

test('cada recepción trae el importe recibido y el total suma el filtro completo', function () {
    $oc = OrdenCompra::factory()->create(['moneda' => 'mxn']);
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'precio_unitario' => 25,
    ]);

    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    // Renglón con precio propio y renglón que cae al precio de la OC.
    EntregaDetalle::factory()->create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 10,
        'precio_unitario' => 100,
    ]);
    EntregaDetalle::factory()->create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 4,
        'precio_unitario' => null,
    ]);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recepciones.data.0.total', 1100)
            ->where('totales_recibidos.mxn', 1100)
        );
});

test('el total no cuenta las recepciones canceladas', function () {
    $partida = OrdenCompraDetalle::factory()->create(['precio_unitario' => 50]);

    $cancelada = Entrega::factory()->create([
        'orden_compra_id' => $partida->orden_compra_id,
        'cancelada_at' => now(),
    ]);
    EntregaDetalle::factory()->create([
        'entrega_id' => $cancelada->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 3,
        'precio_unitario' => null,
    ]);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recepciones.data.0.cancelada', true)
            ->where('recepciones.data.0.total', 150)
            ->where('totales_recibidos', [])
        );
});

test('el total se separa por moneda de la orden de compra', function () {
    foreach (['mxn' => 100, 'usd' => 20] as $moneda => $precio) {
        $partida = OrdenCompraDetalle::factory()->create([
            'orden_compra_id' => OrdenCompra::factory()->create(['moneda' => $moneda])->id,
            'precio_unitario' => $precio,
        ]);
        EntregaDetalle::factory()->create([
            'entrega_id' => Entrega::factory()->create(['orden_compra_id' => $partida->orden_compra_id])->id,
            'orden_compra_detalle_id' => $partida->id,
            'cantidad_recibida' => 2,
            'precio_unitario' => null,
        ]);
    }

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totales_recibidos.mxn', 200)
            ->where('totales_recibidos.usd', 40)
        );
});

test('el reporte incluye el importe recibido', function () {
    $partida = OrdenCompraDetalle::factory()->create(['precio_unitario' => 30]);
    $entrega = Entrega::factory()->create([
        'orden_compra_id' => $partida->orden_compra_id,
        'fecha_entrega' => '2026-07-15',
        'created_at' => '2026-07-15 09:00:00',
    ]);
    EntregaDetalle::factory()->create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 5,
        'precio_unitario' => null,
    ]);

    $filas = (new RecepcionesExport(['fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-07-31']))->collection();

    expect($filas->first()['total'])->toBe(150.0);
});

test('el filtro por tipo acota los resultados', function () {
    Entrega::factory()->create(['tipo' => 'completa']);
    Entrega::factory()->create(['tipo' => 'parcial']);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index', ['tipo' => 'completa']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recepciones.data', 1)
            ->where('recepciones.data.0.tipo', 'completa')
        );
});

test('sin permiso de ver órdenes de compra no puede entrar', function () {
    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso)
        ->get(route('admin.costos.recepciones.index'))
        ->assertForbidden();
});

/** Rubro presupuestal colgado de una obra con nombre conocido. */
function rubroDeObra(string $no): ObraRubro
{
    $obra = Obra::factory()->create(['no' => $no, 'descripcion' => 'Nave industrial']);
    $presupuesto = Presupuesto::factory()->paraObra($obra)->create(['nombre_interno' => null]);

    return ObraRubro::factory()->create(['presupuesto_id' => $presupuesto->id]);
}

test('la obra sale del presupuesto de las partidas, no de la columna legacy de la OC', function () {
    // La OC nace sin `obra_id`, como todas desde el presupuesto polimórfico.
    $oc = OrdenCompra::factory()->create(['obra_id' => null]);
    OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'obra_rubro_id' => rubroDeObra('OP-100')->id,
    ]);
    Entrega::factory()->create([
        'orden_compra_id' => $oc->id,
        'fecha_entrega' => '2026-07-15',
        'created_at' => '2026-07-15 09:00:00',
    ]);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertInertia(fn (Assert $page) => $page->where('recepciones.data.0.obras', ['OP-100']));

    $filas = (new RecepcionesExport(['fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-07-31']))->collection();

    expect($filas->first()['obra'])->toBe('OP-100');
});

test('una OC repartida entre varias obras las lista todas', function () {
    $oc = OrdenCompra::factory()->create(['obra_id' => null]);
    foreach (['OP-100', 'OP-215'] as $no) {
        OrdenCompraDetalle::factory()->create([
            'orden_compra_id' => $oc->id,
            'obra_rubro_id' => rubroDeObra($no)->id,
        ]);
    }
    Entrega::factory()->create([
        'orden_compra_id' => $oc->id,
        'fecha_entrega' => '2026-07-15',
        'created_at' => '2026-07-15 09:00:00',
    ]);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertInertia(fn (Assert $page) => $page->where('recepciones.data.0.obras', ['OP-100', 'OP-215']));

    $filas = (new RecepcionesExport(['fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-07-31']))->collection();

    expect($filas->first()['obra'])->toBe('OP-100 · OP-215');
});

test('si la OC no tiene partidas, la obra se rescata de su solicitud de pago', function () {
    $oc = OrdenCompra::factory()->create(['obra_id' => null, 'tipo_pago' => 'contado']);
    $sp = SolicitudPago::factory()->create(['orden_compra_id' => $oc->id]);
    SolicitudPagoDetalle::factory()->create([
        'solicitud_id' => $sp->id,
        'obra_rubro_id' => rubroDeObra('OP-300')->id,
    ]);
    Entrega::factory()->create(['orden_compra_id' => $oc->id, 'fecha_entrega' => '2026-07-15']);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertInertia(fn (Assert $page) => $page->where('recepciones.data.0.obras', ['OP-300']));
});

test('en último caso la obra se rescata del presupuesto de la requisición', function () {
    $obra = Obra::factory()->create(['no' => 'OP-400', 'descripcion' => 'Bodega']);
    $presupuesto = Presupuesto::factory()->paraObra($obra)->create(['nombre_interno' => null]);
    $requisicion = Requisicion::factory()->create(['presupuesto_id' => $presupuesto->id]);

    $oc = OrdenCompra::factory()->create(['obra_id' => null, 'requisicion_id' => $requisicion->id]);
    Entrega::factory()->create(['orden_compra_id' => $oc->id, 'fecha_entrega' => '2026-07-15']);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertInertia(fn (Assert $page) => $page->where('recepciones.data.0.obras', ['OP-400']));
});

test('sin ningún camino a un presupuesto la columna queda vacía', function () {
    $oc = OrdenCompra::factory()->create(['obra_id' => null, 'requisicion_id' => null]);
    Entrega::factory()->create(['orden_compra_id' => $oc->id, 'fecha_entrega' => '2026-07-15']);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertInertia(fn (Assert $page) => $page->where('recepciones.data.0.obras', []));
});

test('el reporte solo incluye las recepciones dentro del rango de fechas', function () {
    $dentro = Entrega::factory()->create(['created_at' => '2026-07-15 09:00:00']);
    Entrega::factory()->create(['created_at' => '2026-06-30 09:00:00']);
    Entrega::factory()->create(['created_at' => '2026-08-01 09:00:00']);

    $filas = (new RecepcionesExport(['fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-07-31']))->collection();

    expect($filas)->toHaveCount(1)
        ->and($filas->first()['folio'])->toBe($dentro->folio);
});

test('el reporte incluye los límites del rango', function () {
    // Los extremos son días de operación en Mérida: 00:00 del 1 de julio y
    // 23:59 del 31, que en UTC son las 06:00 del 1 y las 05:59 del 1 de agosto.
    Entrega::factory()->create(['created_at' => '2026-07-01 06:00:00']);
    Entrega::factory()->create(['created_at' => '2026-08-01 05:59:00']);

    $filas = (new RecepcionesExport(['fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-07-31']))->collection();

    expect($filas)->toHaveCount(2);
});

test('la pantalla manda las dos fechas: la del documento y la de la entrega', function () {
    // Se capturó el 20 de julio un material que había entrado el 15: son dos
    // datos distintos y la pantalla enseña los dos.
    Entrega::factory()->create([
        'fecha_entrega' => '2026-07-15',
        'created_at' => '2026-07-20 09:00:00',
    ]);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recepciones.data.0.fecha_recepcion', '2026-07-20')
            ->where('recepciones.data.0.fecha_entrega', '2026-07-15')
        );
});

test('la fecha de recepción se pinta en la hora de Mérida, no en UTC', function () {
    // Capturada el 20 de julio a las 19:00 en Mérida: el sello se guarda como
    // el 21 en UTC, pero el documento es del 20 y así debe verse.
    Entrega::factory()->create(['created_at' => '2026-07-21 01:00:00']);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertInertia(fn (Assert $page) => $page->where('recepciones.data.0.fecha_recepcion', '2026-07-20'));
});

test('el reporte recorta por fecha de recepción, no por la de entrega', function () {
    // Entrega de junio capturada en julio: cuenta en el reporte de julio, que es
    // el mes en que se elaboró el documento.
    $capturadaEnJulio = Entrega::factory()->create([
        'fecha_entrega' => '2026-06-28',
        'created_at' => '2026-07-02 09:00:00',
    ]);
    // Y al revés: entrega de julio capturada en agosto, fuera del rango.
    Entrega::factory()->create([
        'fecha_entrega' => '2026-07-30',
        'created_at' => '2026-08-03 09:00:00',
    ]);

    $filas = (new RecepcionesExport(['fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-07-31']))->collection();

    expect($filas)->toHaveCount(1)
        ->and($filas->first()['folio'])->toBe($capturadaEnJulio->folio)
        ->and($filas->first()['fecha_recepcion'])->toBe('02/07/2026')
        ->and($filas->first()['fecha_entrega'])->toBe('28/06/2026');
});

test('lo capturado de noche cuenta en su día de operación, no en el siguiente', function () {
    // 31 de julio a las 19:00 en Mérida: el sello cae al 1 de agosto en UTC,
    // pero el documento es de julio y así tiene que salir en el reporte y en la
    // columna, o el archivo se contradice solo.
    Entrega::factory()->create(['created_at' => '2026-08-01 01:00:00']);

    $filas = (new RecepcionesExport(['fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-07-31']))->collection();

    expect($filas)->toHaveCount(1)
        ->and($filas->first()['fecha_recepcion'])->toBe('31/07/2026');
});

test('el reporte arrastra el filtro de tipo de la pantalla', function () {
    Entrega::factory()->create(['created_at' => '2026-07-15 09:00:00', 'tipo' => 'completa']);
    Entrega::factory()->create(['created_at' => '2026-07-16 09:00:00', 'tipo' => 'parcial']);

    $filas = (new RecepcionesExport([
        'fecha_inicio' => '2026-07-01',
        'fecha_fin' => '2026-07-31',
        'tipo' => 'completa',
    ]))->collection();

    expect($filas)->toHaveCount(1)
        ->and($filas->first()['tipo'])->toBe('Completa');
});

test('descarga el excel del rango pedido', function () {
    Entrega::factory()->create(['created_at' => '2026-07-15 09:00:00']);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.exportar', [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
        ]))
        ->assertOk()
        ->assertDownload('recepciones-20260701-a-20260731.xlsx');
});

test('rechaza un rango con la fecha final antes de la inicial', function () {
    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.exportar', [
            'fecha_inicio' => '2026-07-31',
            'fecha_fin' => '2026-07-01',
        ]))
        ->assertSessionHasErrors('fecha_fin');
});

test('exige el rango de fechas', function () {
    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.exportar'))
        ->assertSessionHasErrors(['fecha_inicio', 'fecha_fin']);
});

test('sin permiso no puede descargar el reporte', function () {
    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso)
        ->get(route('admin.costos.recepciones.exportar', [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
        ]))
        ->assertForbidden();
});
