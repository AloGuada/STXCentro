<?php

use App\Models\Prod\CategoriaEmpleado;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\TipoPagoExtra;
use App\Models\User;
use App\Services\Prod\GeneradorLiquidaciones;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->service = app(GeneradorLiquidaciones::class);
    $this->grupo = GrupoTrabajo::factory()->create();
    $this->destajo = Destajo::factory()->create([
        'cerrado' => false,
        'fecha_inicio' => '2026-03-02',
        'fecha_fin' => '2026-03-08',
    ]);
});

/** 30 piezas de 10 kg a $5/kg: 300 kg y $1,500 de produccion. */
function marcaDe1500(): App\Models\Concepto
{
    $marca = marcaConPiezas(30, ['peso_unitario' => 10.000]);
    tarifaDeMarca($marca, 5.0000);
    obraPagaProcesos($marca->obra_id);

    return $marca;
}

test('generar reparte el total por el peso de la categoria', function () {
    $marca = marcaDe1500();

    GrupoEmpleado::factory()->create([
        'grupo_trabajo_id' => $this->grupo->id,
        'categoria_empleado_id' => CategoriaEmpleado::factory()->create(['valor' => 700])->id,
    ]);
    GrupoEmpleado::factory()->create([
        'grupo_trabajo_id' => $this->grupo->id,
        'categoria_empleado_id' => CategoriaEmpleado::factory()->create(['valor' => 300])->id,
    ]);

    capturarPiezas($marca->piezas, $this->grupo, '2026-03-04');

    $this->service->generar($this->destajo);

    // Sin asistencia capturada el sueldo base es 0, asi que los 1500 son
    // excedente y se reparten 70/30 por peso.
    $liq = $this->destajo->liquidaciones()->with('empleados')->first();

    expect((float) $liq->total_final)->toBe(1500.0)
        ->and((float) $liq->empleados->firstWhere('categoria_valor', 700)->monto_asignado)->toBe(1050.0)
        ->and((float) $liq->empleados->firstWhere('categoria_valor', 300)->monto_asignado)->toBe(450.0)
        ->and($this->destajo->fresh()->cerrado)->toBeTrue();
});

test('piezasSinPrecio detecta la marca sin tarifa para ese proceso', function () {
    $conPrecio = marcaConPiezas(1, ['marca' => 'CON']);
    tarifaDeMarca($conPrecio, 5);
    obraPagaProcesos($conPrecio->obra_id);

    $sinPrecio = marcaConPiezas(1, [
        'obra_id' => $conPrecio->obra_id,
        'catalogo_id' => $conPrecio->catalogo_id,
        'marca' => 'SIN',
    ]);

    capturarPiezas($conPrecio->piezas, $this->grupo, '2026-03-04');
    capturarPiezas($sinPrecio->piezas, $this->grupo, '2026-03-04');

    $piezas = $this->service->piezasSinPrecio($this->destajo);

    expect($piezas)->toHaveCount(1)
        ->and($piezas->first()['marca'])->toBe('SIN')
        ->and($piezas->first()['proceso'])->toBe('Soldadura');
});

test('la marca con tarifa en soldadura pero no en pintura se reporta', function () {
    $marca = marcaConPiezas(1);
    $pintura = proceso('Pintura');

    tarifaDeMarca($marca, 5);
    obraPagaProcesos($marca->obra_id, proceso(), $pintura);

    capturarPiezas($marca->piezas, $this->grupo, '2026-03-04', proceso: $pintura);

    $piezas = $this->service->piezasSinPrecio($this->destajo);

    expect($piezas)->toHaveCount(1)
        ->and($piezas->first()['proceso'])->toBe('Pintura');
});

test('ordenDePago (abierto) calcula del preview con secciones y empleados', function () {
    $marca = marcaDe1500();
    GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $this->grupo->id]);

    capturarPiezas($marca->piezas, $this->grupo, '2026-03-04');

    $tipo = TipoPagoExtra::create(['descripcion' => 'Horas Extra', 'orden' => 1, 'desgloce' => false]);
    PagoExtra::create([
        'destajo_id' => $this->destajo->id,
        'grupo_trabajo_id' => $this->grupo->id,
        'tipo_id' => $tipo->id,
        'descripcion' => 'Sabado',
        'precio' => 100,
        'dias' => 2,
        'personas' => 3,
    ]);

    $orden = $this->service->ordenDePago($this->destajo);

    expect($orden)->toHaveCount(1);
    $g = $orden->first();

    // 300 kg x $5 = 1500 produccion; 100 x 2 x 3 = 600 extras.
    expect($g['total_produccion'])->toBe(1500.0)
        ->and($g['total_extras'])->toBe(600.0)
        ->and($g['total_final'])->toBe(2100.0)
        // Las 30 piezas del mismo modelo y proceso salen en un solo renglon.
        ->and($g['piezas'])->toHaveCount(1)
        ->and($g['piezas'][0]['pzs'])->toBe(30)
        ->and($g['empleados'][0]['monto'])->toBe(2100.0);

    $seccionHorasExtra = collect($g['secciones'])->firstWhere('tipo', 'Horas Extra');

    expect($seccionHorasExtra['subtotal'])->toBe(600.0)
        ->and($seccionHorasExtra['pagos'])->toHaveCount(1);
});

test('ordenDePago separa un renglon por proceso', function () {
    $marca = marcaConPiezas(2, ['peso_unitario' => 10.000]);
    $pintura = proceso('Pintura');

    obraPagaProcesos($marca->obra_id, proceso(), $pintura);
    $grupoPrecio = tarifaDeMarca($marca, 5);
    tarifaDeMarca($marca, 2, $pintura, $grupoPrecio);

    GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $this->grupo->id]);

    capturarPiezas($marca->piezas, $this->grupo, '2026-03-04');
    capturarPiezas($marca->piezas, $this->grupo, '2026-03-04', proceso: $pintura);

    $piezas = $this->service->ordenDePago($this->destajo)->first()['piezas'];

    expect($piezas)->toHaveCount(2)
        ->and(collect($piezas)->pluck('proceso')->sort()->values()->all())->toBe(['Pintura', 'Soldadura'])
        // 2 pz x 10 kg = 20 kg: $100 soldando y $40 pintando.
        ->and(collect($piezas)->sum('importe'))->toBe(140.0);
});

test('ordenDePago (cerrado) lee de las liquidaciones inmutables', function () {
    $marca = marcaDe1500();
    GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $this->grupo->id]);

    capturarPiezas($marca->piezas, $this->grupo, '2026-03-04');

    $this->service->generar($this->destajo);

    $orden = $this->service->ordenDePago($this->destajo->fresh());

    expect($orden)->toHaveCount(1)
        ->and($orden->first()['total_final'])->toBe(1500.0)
        ->and($orden->first()['piezas'])->toHaveCount(1)
        ->and($orden->first()['piezas'][0]['pzs'])->toBe(30);
});
