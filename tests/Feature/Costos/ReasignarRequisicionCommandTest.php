<?php

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\Entrega;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\SolicitudPago;
use App\Services\Costos\ApartadoPresupuestal;
use App\Services\Costos\ReasignacionCentroCostosRequisicion;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Una requisición liberada a una OC con su cargo aplicado, capturada en el
 * centro de costos equivocado.
 *
 * @return array{requisicion: Requisicion, detalle: RequisicionDetalle, oc: OrdenCompra, partida: OrdenCompraDetalle, origen: ObraRubro, destino: ObraRubro}
 */
function requisicionLiberadaEnOtraObra(string $moneda = 'mxn', float $tc = 1): array
{
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000]);

    $requisicion = Requisicion::factory()->create([
        'folio' => 'REQ-2609-0001',
        'estatus' => 'liberada',
        'presupuesto_id' => $origen->presupuesto_id,
    ]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $requisicion->id,
        'obra_rubro_id' => $origen->id,
    ]);

    $oc = OrdenCompra::factory()->pendienteEntrega()->create([
        'requisicion_id' => $requisicion->id,
        'moneda' => $moneda,
        'tipo_cambio' => $tc,
        'total' => 1160,
    ]);
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'obra_rubro_id' => $origen->id,
        'cantidad' => 10,
        'precio_unitario' => 100,
        'subtotal' => 1000,
    ]);
    $oc->load('detalles')->aplicarImpactoPresupuestal();

    return [
        'requisicion' => $requisicion,
        'detalle' => $detalle,
        'oc' => $oc,
        'partida' => $partida,
        'origen' => $origen->fresh(),
        'destino' => $destino->fresh(),
    ];
}

function reasignar(array $datos, array $extra = []): \Illuminate\Testing\PendingCommand
{
    return test()->artisan('costos:reasignar-requisicion', array_merge([
        'folio' => $datos['requisicion']->folio,
        '--mapa' => ["{$datos['origen']->id}:{$datos['destino']->id}"],
        '--motivo' => 'Se capturó en la obra equivocada',
        '--force' => true,
    ], $extra));
}

test('sin --force sólo muestra el plan', function () {
    $datos = requisicionLiberadaEnOtraObra();

    reasignar($datos, ['--force' => false])
        ->expectsOutputToContain('DRY-RUN')
        ->assertSuccessful();

    expect((int) $datos['partida']->fresh()->obra_rubro_id)->toBe($datos['origen']->id)
        ->and((float) $datos['origen']->fresh()->acumulado)->toBe(1000.0);
});

test('mueve partidas, solicitud de pago y el cargo de la OC al centro nuevo', function () {
    $datos = requisicionLiberadaEnOtraObra();
    $solicitud = SolicitudPago::factory()->create(['orden_compra_id' => $datos['oc']->id]);
    $renglon = $solicitud->detalles()->create([
        'obra_rubro_id' => $datos['origen']->id,
        'concepto' => 'Pago de contado',
        'cantidad' => 1,
        'precio_unitario' => 1160,
        'subtotal' => 1160,
    ]);

    reasignar($datos)->assertSuccessful();

    expect((float) $datos['origen']->fresh()->acumulado)->toBe(0.0)
        ->and((float) $datos['destino']->fresh()->acumulado)->toBe(1000.0)
        ->and((int) $datos['detalle']->fresh()->obra_rubro_id)->toBe($datos['destino']->id)
        ->and((int) $datos['partida']->fresh()->obra_rubro_id)->toBe($datos['destino']->id)
        ->and((int) $renglon->fresh()->obra_rubro_id)->toBe($datos['destino']->id)
        ->and((int) $datos['requisicion']->fresh()->presupuesto_id)->toBe($datos['destino']->presupuesto_id);

    $cargos = RubroAfectado::query()->where('entrada_type', OrdenCompra::class)->where('entrada_id', $datos['oc']->id)->orderBy('id')->get();

    expect($cargos)->toHaveCount(2)
        ->and($cargos[0]->estatus)->toBe(RubroAfectadoEstatus::Cancelado)
        ->and($cargos[0]->descripcion)->toContain('Se capturó en la obra equivocada')
        ->and($cargos[1]->estatus)->toBe(RubroAfectadoEstatus::Aplicado)
        ->and((int) $cargos[1]->obra_rubro_id)->toBe($datos['destino']->id);

    expect(Activity::query()->where('description', 'Centros de costos reasignados por comando')->exists())->toBeTrue();
});

test('el cargo en divisa conserva sus pesos aunque la conciliación los haya corregido', function () {
    $datos = requisicionLiberadaEnOtraObra('usd', 17);
    $cargo = RubroAfectado::query()->where('entrada_type', OrdenCompra::class)->firstOrFail();

    // La conciliación de tipo de cambio dejó el cargo en pesos reales.
    $cargo->update(['monto' => 17234.56]);
    $datos['origen']->update(['acumulado' => 17234.56]);

    reasignar($datos)->assertSuccessful();

    $nuevo = RubroAfectado::query()->where('entrada_type', OrdenCompra::class)->where('estatus', RubroAfectadoEstatus::Aplicado->value)->firstOrFail();

    expect((float) $nuevo->monto)->toBe(17234.56)
        ->and($nuevo->moneda)->toBe('usd')
        ->and((float) $nuevo->monto_origen)->toBe(1000.0)
        ->and((float) $nuevo->tipo_cambio)->toBe(17.0)
        ->and((float) $datos['destino']->fresh()->acumulado)->toBe(17234.56)
        ->and((float) $datos['origen']->fresh()->acumulado)->toBe(0.0);
});

test('el apartado de una requisición en aprobación sigue siendo apartado', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000]);
    $requisicion = Requisicion::factory()->pendienteAprobacion()->create(['folio' => 'REQ-2609-0002']);
    RequisicionDetalle::factory()->create(['requisicion_id' => $requisicion->id, 'obra_rubro_id' => $origen->id]);
    $hasta = Carbon::today()->addDays(5);
    app(ApartadoPresupuestal::class)->aplicarCargo($requisicion, $origen->id, 500, RubroAfectadoEstatus::Apartado, apartadoHasta: $hasta);

    reasignar(['requisicion' => $requisicion, 'origen' => $origen, 'destino' => $destino])->assertSuccessful();

    $nuevo = $requisicion->rubrosAfectados()->where('estatus', RubroAfectadoEstatus::Apartado->value)->firstOrFail();

    expect((int) $nuevo->obra_rubro_id)->toBe($destino->id)
        ->and($nuevo->apartado_hasta->toDateString())->toBe($hasta->toDateString())
        ->and((float) $origen->fresh()->apartado)->toBe(0.0)
        ->and((float) $destino->fresh()->apartado)->toBe(500.0)
        ->and((float) $destino->fresh()->acumulado)->toBe(0.0);
});

test('se niega si alguna OC ya tiene material recibido', function () {
    $datos = requisicionLiberadaEnOtraObra();
    Entrega::factory()->create(['orden_compra_id' => $datos['oc']->id]);

    reasignar($datos)
        ->expectsOutputToContain('Hay material recibido')
        ->assertFailed();

    expect((int) $datos['partida']->fresh()->obra_rubro_id)->toBe($datos['origen']->id)
        ->and((float) $datos['origen']->fresh()->acumulado)->toBe(1000.0);
});

test('una recepción cancelada no frena', function () {
    $datos = requisicionLiberadaEnOtraObra();
    Entrega::factory()->create(['orden_compra_id' => $datos['oc']->id, 'cancelada_at' => now()]);

    reasignar($datos)->assertSuccessful();

    expect((int) $datos['partida']->fresh()->obra_rubro_id)->toBe($datos['destino']->id);
});

test('las OC canceladas no se tocan', function () {
    $datos = requisicionLiberadaEnOtraObra();
    $cancelada = OrdenCompra::factory()->create(['requisicion_id' => $datos['requisicion']->id, 'estatus' => 'cancelada']);
    $partidaCancelada = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $cancelada->id,
        'obra_rubro_id' => $datos['origen']->id,
    ]);

    reasignar($datos)
        ->expectsOutputToContain("OC canceladas que no se tocan: {$cancelada->folio}")
        ->assertSuccessful();

    expect((int) $partidaCancelada->fresh()->obra_rubro_id)->toBe($datos['origen']->id);
});

test('un presupuesto destino cerrado no detiene la reasignación pero la marca', function () {
    $datos = requisicionLiberadaEnOtraObra();
    $cerrado = ObraRubro::factory()->create([
        'presupuesto_id' => Presupuesto::factory()->cerrado(),
        'presupuestado' => 100,
    ]);

    reasignar(array_merge($datos, ['destino' => $cerrado]))
        ->expectsOutputToContain('está cerrado')
        ->expectsOutputToContain('queda sobregirado')
        ->assertSuccessful();

    expect($datos['requisicion']->fresh()->sobre_obra_cerrada)->toBeTrue()
        ->and((float) $cerrado->fresh()->acumulado)->toBe(1000.0);
});

test('lo que no está en el mapa se queda y el encabezado queda multipresupuesto', function () {
    $datos = requisicionLiberadaEnOtraObra();
    $otro = ObraRubro::factory()->create();
    $segunda = RequisicionDetalle::factory()->create(['requisicion_id' => $datos['requisicion']->id, 'obra_rubro_id' => $otro->id]);

    reasignar($datos)->assertSuccessful();

    expect((int) $segunda->fresh()->obra_rubro_id)->toBe($otro->id)
        ->and($datos['requisicion']->fresh()->presupuesto_id)->toBeNull();
});

test('rechaza mapas que no aplican', function (callable $mapa, string $error) {
    $datos = requisicionLiberadaEnOtraObra();

    reasignar($datos, ['--mapa' => $mapa($datos)])
        ->expectsOutputToContain($error)
        ->assertFailed();
})->with([
    'rubro que la requisición no usa' => [fn (array $d) => ["{$d['destino']->id}:{$d['origen']->id}"], 'no carga al centro de costos'],
    'destino inexistente' => [fn (array $d) => ["{$d['origen']->id}:999999"], 'No existe el centro de costos 999999'],
    'encadenado' => [fn (array $d) => ["{$d['origen']->id}:{$d['destino']->id}", "{$d['destino']->id}:{$d['origen']->id}"], 'es destino y también origen'],
]);

test('con --force exige motivo', function () {
    $datos = requisicionLiberadaEnOtraObra();

    reasignar($datos, ['--motivo' => null])
        ->expectsOutputToContain('Indica --motivo')
        ->assertExitCode(2);

    expect((int) $datos['partida']->fresh()->obra_rubro_id)->toBe($datos['origen']->id);
});

test('un mapa mal escrito se rechaza', function () {
    $datos = requisicionLiberadaEnOtraObra();

    reasignar($datos, ['--mapa' => ['12-34']])
        ->expectsOutputToContain('no tiene la forma viejo:nuevo')
        ->assertExitCode(2);
});

test('sin --mapa lista los centros de costos de la requisición y sus destinos', function () {
    $datos = requisicionLiberadaEnOtraObra();
    $hermano = ObraRubro::factory()->create(['presupuesto_id' => $datos['origen']->presupuesto_id]);

    test()->artisan('costos:reasignar-requisicion', ['folio' => $datos['requisicion']->folio])
        ->expectsOutputToContain('origen del --mapa')
        ->expectsOutputToContain('1,000.00')
        ->expectsOutputToContain('destino del --mapa')
        ->assertSuccessful();

    $servicio = app(ReasignacionCentroCostosRequisicion::class);

    expect($servicio->centrosEnUso($datos['requisicion']))->toBe([[
        'id' => $datos['origen']->id,
        'presupuesto_id' => $datos['origen']->presupuesto_id,
        'presupuesto' => $datos['origen']->presupuesto->nombreMostrar(),
        'rubro' => trim($datos['origen']->rubro->codigo.' '.$datos['origen']->rubro->descripcion),
        'partidas' => 1,
        'cargado' => 1000.0,
    ]])
        ->and(array_column($servicio->centrosDePresupuestos([$datos['origen']->presupuesto_id]), 'id'))
        ->toBe([$datos['origen']->id, $hermano->id]);

    expect((int) $datos['partida']->fresh()->obra_rubro_id)->toBe($datos['origen']->id);
});

test('--presupuesto agrega los centros de otra obra a los destinos', function () {
    $datos = requisicionLiberadaEnOtraObra();

    test()->artisan('costos:reasignar-requisicion', [
        'folio' => $datos['requisicion']->folio,
        '--presupuesto' => [$datos['destino']->presupuesto_id],
    ])
        ->expectsOutputToContain($datos['destino']->presupuesto->nombreMostrar())
        ->assertSuccessful();
});
