<?php

use App\Exports\Costos\RecepcionesExport;
use App\Models\Costos\Entrega;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\SolicitudPago;
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

test('la obra sale del presupuesto de las partidas, no de la columna legacy de la OC', function () {
    $obra = Obra::factory()->create(['no' => 'OP-100', 'descripcion' => 'Nave industrial']);
    $presupuesto = Presupuesto::factory()->paraObra($obra)->create(['nombre_interno' => null]);
    $obraRubro = ObraRubro::factory()->create(['presupuesto_id' => $presupuesto->id]);

    // La OC nace sin `obra_id`, como todas desde el presupuesto polimórfico.
    $oc = OrdenCompra::factory()->create(['obra_id' => null]);
    OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'obra_rubro_id' => $obraRubro->id,
    ]);
    Entrega::factory()->create(['orden_compra_id' => $oc->id, 'fecha_entrega' => '2026-07-15']);

    $this->actingAs($this->user)
        ->get(route('admin.costos.recepciones.index'))
        ->assertInertia(fn (Assert $page) => $page->where('recepciones.data.0.obra', 'OP-100'));

    $filas = (new RecepcionesExport(['fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-07-31']))->collection();

    expect($filas->first()['obra'])->toBe('OP-100');
});

test('el reporte solo incluye las recepciones dentro del rango de fechas', function () {
    $dentro = Entrega::factory()->create(['fecha_entrega' => '2026-07-15']);
    Entrega::factory()->create(['fecha_entrega' => '2026-06-30']);
    Entrega::factory()->create(['fecha_entrega' => '2026-08-01']);

    $filas = (new RecepcionesExport(['fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-07-31']))->collection();

    expect($filas)->toHaveCount(1)
        ->and($filas->first()['folio'])->toBe($dentro->folio);
});

test('el reporte incluye los límites del rango', function () {
    Entrega::factory()->create(['fecha_entrega' => '2026-07-01']);
    Entrega::factory()->create(['fecha_entrega' => '2026-07-31']);

    $filas = (new RecepcionesExport(['fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-07-31']))->collection();

    expect($filas)->toHaveCount(2);
});

test('el reporte arrastra el filtro de tipo de la pantalla', function () {
    Entrega::factory()->create(['fecha_entrega' => '2026-07-15', 'tipo' => 'completa']);
    Entrega::factory()->create(['fecha_entrega' => '2026-07-16', 'tipo' => 'parcial']);

    $filas = (new RecepcionesExport([
        'fecha_inicio' => '2026-07-01',
        'fecha_fin' => '2026-07-31',
        'tipo' => 'completa',
    ]))->collection();

    expect($filas)->toHaveCount(1)
        ->and($filas->first()['tipo'])->toBe('Completa');
});

test('descarga el excel del rango pedido', function () {
    Entrega::factory()->create(['fecha_entrega' => '2026-07-15']);

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
