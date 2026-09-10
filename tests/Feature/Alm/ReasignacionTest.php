<?php

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Asignacion;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Costos\Producto;
use App\Models\Obra;
use App\Models\User;
use App\Services\Alm\AlmacenLedger;
use Spatie\Permission\Models\Permission;

/**
 * Reasignar cambia de dueño, no de bodega.
 *
 * Lo que estas pruebas cuidan es que el saldo no se entere: la pareja de
 * asientos suma cero y el valor del inventario termina donde empezó. Si eso deja
 * de ser cierto, reasignar estaría inventando o destruyendo material.
 */
beforeEach(function () {
    Permission::firstOrCreate(['name' => 'alm.asignaciones.reasignar', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'alm.almacenes.ver-todos', 'guard_name' => 'web']);

    $this->almacenista = User::factory()->create();
    $this->almacenista->givePermissionTo(['alm.asignaciones.reasignar', 'alm.almacenes.ver-todos']);

    $this->almacen = Almacen::factory()->create();
    $this->producto = Producto::factory()->create(['controla_inventario' => true]);
    $this->obra = Obra::factory()->create();
});

function saldoDeObra(Obra $obra): float
{
    return (float) Asignacion::where('obra_id', $obra->id)->value('cantidad');
}

function sembrarParaReasignar(float $cantidad, float $costo = 10, ?int $obraId = null): Existencia
{
    app(AlmacenLedger::class)->registrarPorProducto(
        test()->almacen->id, test()->producto->id, MovimientoTipo::Entrada, $cantidad, $costo, obraId: $obraId
    );

    return Existencia::firstOrFail();
}

function payloadReasignacion(array $payload = []): array
{
    return array_replace([
        'existencia_id' => Existencia::firstOrFail()->id,
        'de_obra_id' => null,
        'a_obra_id' => test()->obra->id,
        'cantidad' => 30,
        'motivo' => 'Se ocupa en el montaje de la semana',
    ], $payload);
}

test('reparte material libre hacia una obra', function () {
    $existencia = sembrarParaReasignar(100);

    $this->actingAs($this->almacenista)
        ->post(route('admin.alm.asignaciones.reasignar'), payloadReasignacion())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $existencia->refresh();

    // Es la vía normal por la que el inventario que ya estaba en bodega gana
    // dueño: no hay recepción que lo derive.
    expect(saldoDeObra($this->obra))->toBe(30.0)
        ->and($existencia->libre())->toBe(70.0)
        ->and((float) $existencia->cantidad)->toBe(100.0);
});

test('el saldo y el valor del inventario no se enteran', function () {
    $existencia = sembrarParaReasignar(100, 12.5);

    $this->actingAs($this->almacenista)
        ->post(route('admin.alm.asignaciones.reasignar'), payloadReasignacion());

    $existencia->refresh();

    // Cambió de dueño, no de bodega: si el valor se moviera, reasignar estaría
    // inventando o destruyendo dinero.
    expect((float) $existencia->cantidad)->toBe(100.0)
        ->and((float) $existencia->valor)->toBe(1250.0)
        ->and((float) $existencia->costo_promedio)->toBe(12.5);
});

test('deja dos asientos que suman cero', function () {
    sembrarParaReasignar(100);

    $this->actingAs($this->almacenista)
        ->post(route('admin.alm.asignaciones.reasignar'), payloadReasignacion());

    $asientos = Movimiento::where('tipo', MovimientoTipo::Reasignacion->value)->cronologico()->get();

    expect($asientos)->toHaveCount(2)
        ->and((float) $asientos->sum('cantidad'))->toBe(0.0)
        // Uno descarga el origen y otro carga el destino; el kardex lo explica
        // sin necesidad de un segundo libro.
        ->and($asientos[0]->obra_id)->toBeNull()
        ->and($asientos[1]->obra_id)->toBe($this->obra->id)
        ->and($asientos[0]->observaciones)->toContain('Reasignación')
        ->and($asientos[0]->observaciones)->toContain('montaje de la semana');
});

test('pasa material de una obra a otra', function () {
    sembrarParaReasignar(100, 10, $this->obra->id);
    $destino = Obra::factory()->create();

    $this->actingAs($this->almacenista)
        ->post(route('admin.alm.asignaciones.reasignar'), payloadReasignacion([
            'de_obra_id' => $this->obra->id,
            'a_obra_id' => $destino->id,
            'cantidad' => 40,
        ]))
        ->assertSessionHasNoErrors();

    expect(saldoDeObra($this->obra))->toBe(60.0)
        ->and(saldoDeObra($destino))->toBe(40.0)
        ->and(Existencia::firstOrFail()->libre())->toBe(0.0);
});

test('suelta material de una obra de vuelta a libre', function () {
    $existencia = sembrarParaReasignar(50, 10, $this->obra->id);

    $this->actingAs($this->almacenista)
        ->post(route('admin.alm.asignaciones.reasignar'), payloadReasignacion([
            'de_obra_id' => $this->obra->id,
            'a_obra_id' => null,
            'cantidad' => 50,
        ]))
        ->assertSessionHasNoErrors();

    expect(saldoDeObra($this->obra))->toBe(0.0)
        ->and($existencia->refresh()->libre())->toBe(50.0);
});

test('no reasigna más de lo que hay en el origen', function () {
    sembrarParaReasignar(100, 10, $this->obra->id);
    $destino = Obra::factory()->create();

    // Sin esta validación el ledger haría lo suyo —consumir lo de la obra y
    // luego lo libre— y se llevaría 20 de lo libre en silencio.
    $this->actingAs($this->almacenista)
        ->post(route('admin.alm.asignaciones.reasignar'), payloadReasignacion([
            'de_obra_id' => $this->obra->id,
            'a_obra_id' => $destino->id,
            'cantidad' => 120,
        ]))
        ->assertSessionHasErrors('cantidad');

    expect(saldoDeObra($this->obra))->toBe(100.0)
        ->and(saldoDeObra($destino))->toBe(0.0)
        ->and(Movimiento::where('tipo', MovimientoTipo::Reasignacion->value)->count())->toBe(0);
});

test('el origen y el destino no pueden ser el mismo', function () {
    sembrarParaReasignar(100, 10, $this->obra->id);

    $this->actingAs($this->almacenista)
        ->post(route('admin.alm.asignaciones.reasignar'), payloadReasignacion([
            'de_obra_id' => $this->obra->id,
            'a_obra_id' => $this->obra->id,
        ]))
        ->assertSessionHasErrors('a_obra_id');
});

test('sin permiso no se reasigna', function () {
    sembrarParaReasignar(100);

    $intruso = User::factory()->create();

    $this->actingAs($intruso)
        ->post(route('admin.alm.asignaciones.reasignar'), payloadReasignacion())
        ->assertForbidden();

    expect(Asignacion::count())->toBe(0);
});

test('no se reparte el material de un almacén que no se ve', function () {
    sembrarParaReasignar(100);

    // El permiso abre la acción; la visibilidad decide sobre qué material.
    $ajeno = User::factory()->create();
    $ajeno->givePermissionTo('alm.asignaciones.reasignar');

    $this->actingAs($ajeno)
        ->post(route('admin.alm.asignaciones.reasignar'), payloadReasignacion())
        ->assertNotFound();

    expect(Asignacion::count())->toBe(0);
});

test('los totales del kardex no cuentan la reasignación', function () {
    sembrarParaReasignar(100);

    $this->almacenista->givePermissionTo(
        Permission::firstOrCreate(['name' => 'alm.kardex.ver', 'guard_name' => 'web'])
    );

    $this->actingAs($this->almacenista)
        ->post(route('admin.alm.asignaciones.reasignar'), payloadReasignacion());

    // La entrada de 100 y nada más: la pareja de la reasignación inflaría los
    // dos lados con material que nunca se movió.
    $this->actingAs($this->almacenista)
        ->get(route('admin.alm.kardex.index'))
        ->assertInertia(fn ($page) => $page
            // Enteros y no floats: el payload viaja por JSON y 100.0 llega
            // como 100.
            ->where('totales.entradas', 100)
            ->where('totales.salidas', 0)
            ->where('totales.movimientos', 1));
});
