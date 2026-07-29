<?php

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\CategoriaEmpleado;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Registro;
use App\Models\Prod\TipoPagoExtra;
use App\Models\User;
use App\Services\Prod\GeneradorLiquidaciones;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->service = app(GeneradorLiquidaciones::class);
});

test('generar reparte el total por el peso de la categoria', function () {
    $obra = Obra::factory()->create();
    $concepto = Concepto::factory()->create(['obra_id' => $obra->id, 'peso_unitario' => 10.000]);
    $gp = GrupoPrecio::factory()->create(['obra_id' => $obra->id, 'precio_kilo' => 5.0000]);
    GrupoPrecioConcepto::create(['concepto_id' => $concepto->id, 'grupo_precio_id' => $gp->id]);

    $grupo = GrupoTrabajo::factory()->create();
    GrupoEmpleado::factory()->create([
        'grupo_trabajo_id' => $grupo->id,
        'categoria_empleado_id' => CategoriaEmpleado::factory()->create(['valor' => 700])->id,
    ]);
    GrupoEmpleado::factory()->create([
        'grupo_trabajo_id' => $grupo->id,
        'categoria_empleado_id' => CategoriaEmpleado::factory()->create(['valor' => 300])->id,
    ]);

    $destajo = Destajo::factory()->create(['fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-03-08']);
    Registro::factory()->create([
        'fecha' => '2026-03-04',
        'concepto_id' => $concepto->id,
        'grupo_trabajo_id' => $grupo->id,
        'cantidad' => 30,
    ]);

    $this->service->generar($destajo);

    // 30 * 10kg = 300kg * 5 = 1500. Sin asistencia capturada el sueldo base es
    // 0, asi que los 1500 son excedente y se reparten 70/30 por peso.
    $liq = $destajo->liquidaciones()->with('empleados')->first();
    expect((float) $liq->total_final)->toBe(1500.0);
    expect((float) $liq->empleados->firstWhere('categoria_valor', 700)->monto_asignado)->toBe(1050.0);
    expect((float) $liq->empleados->firstWhere('categoria_valor', 300)->monto_asignado)->toBe(450.0);
    expect($destajo->fresh()->cerrado)->toBeTrue();
});

test('piezasSinPrecio detecta conceptos sin grupo de precio', function () {
    $obra = Obra::factory()->create();
    $conPrecio = Concepto::factory()->create(['obra_id' => $obra->id, 'marca' => 'CON']);
    $sinPrecio = Concepto::factory()->create(['obra_id' => $obra->id, 'marca' => 'SIN']);
    $gp = GrupoPrecio::factory()->create(['obra_id' => $obra->id]);
    GrupoPrecioConcepto::create(['concepto_id' => $conPrecio->id, 'grupo_precio_id' => $gp->id]);

    $grupo = GrupoTrabajo::factory()->create();
    $destajo = Destajo::factory()->create(['fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-03-08']);
    Registro::factory()->create(['fecha' => '2026-03-04', 'concepto_id' => $conPrecio->id, 'grupo_trabajo_id' => $grupo->id, 'cantidad' => 5]);
    Registro::factory()->create(['fecha' => '2026-03-04', 'concepto_id' => $sinPrecio->id, 'grupo_trabajo_id' => $grupo->id, 'cantidad' => 5]);

    $piezas = $this->service->piezasSinPrecio($destajo);

    expect($piezas)->toHaveCount(1);
    expect($piezas->first()['marca'])->toBe('SIN');
});

test('ordenDePago (abierto) calcula del preview con secciones y empleados', function () {
    $obra = Obra::factory()->create();
    $concepto = Concepto::factory()->create(['obra_id' => $obra->id, 'peso_unitario' => 10.000]);
    $gp = GrupoPrecio::factory()->create(['obra_id' => $obra->id, 'precio_kilo' => 5.0000]);
    GrupoPrecioConcepto::create(['concepto_id' => $concepto->id, 'grupo_precio_id' => $gp->id]);

    $grupo = GrupoTrabajo::factory()->create();
    GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $grupo->id]);

    $destajo = Destajo::factory()->create(['cerrado' => false, 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-03-08']);
    Registro::factory()->create(['fecha' => '2026-03-04', 'concepto_id' => $concepto->id, 'grupo_trabajo_id' => $grupo->id, 'cantidad' => 30]);

    $tipo = TipoPagoExtra::create(['descripcion' => 'Horas Extra', 'orden' => 1, 'desgloce' => false]);
    PagoExtra::create([
        'destajo_id' => $destajo->id,
        'grupo_trabajo_id' => $grupo->id,
        'tipo_id' => $tipo->id,
        'descripcion' => 'Sabado',
        'precio' => 100,
        'dias' => 2,
        'personas' => 3,
    ]);

    $orden = $this->service->ordenDePago($destajo);

    expect($orden)->toHaveCount(1);
    $g = $orden->first();
    // 30 * 10kg = 300kg * 5 = 1500 produccion; 100*2*3 = 600 extras
    expect($g['total_produccion'])->toBe(1500.0)
        ->and($g['total_extras'])->toBe(600.0)
        ->and($g['total_final'])->toBe(2100.0)
        ->and($g['piezas'])->toHaveCount(1)
        ->and($g['empleados'][0]['monto'])->toBe(2100.0);

    $seccionHorasExtra = collect($g['secciones'])->firstWhere('tipo', 'Horas Extra');
    expect($seccionHorasExtra['subtotal'])->toBe(600.0)
        ->and($seccionHorasExtra['pagos'])->toHaveCount(1);
});

test('ordenDePago (cerrado) lee de las liquidaciones inmutables', function () {
    $obra = Obra::factory()->create();
    $concepto = Concepto::factory()->create(['obra_id' => $obra->id, 'peso_unitario' => 10.000]);
    $gp = GrupoPrecio::factory()->create(['obra_id' => $obra->id, 'precio_kilo' => 5.0000]);
    GrupoPrecioConcepto::create(['concepto_id' => $concepto->id, 'grupo_precio_id' => $gp->id]);

    $grupo = GrupoTrabajo::factory()->create();
    GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $grupo->id]);

    $destajo = Destajo::factory()->create(['cerrado' => false, 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-03-08']);
    Registro::factory()->create(['fecha' => '2026-03-04', 'concepto_id' => $concepto->id, 'grupo_trabajo_id' => $grupo->id, 'cantidad' => 30]);

    $this->service->generar($destajo);

    $orden = $this->service->ordenDePago($destajo->fresh());

    expect($orden)->toHaveCount(1);
    expect($orden->first()['total_final'])->toBe(1500.0)
        ->and($orden->first()['piezas'])->toHaveCount(1);
});
