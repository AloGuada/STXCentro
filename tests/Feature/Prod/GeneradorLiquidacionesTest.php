<?php

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Registro;
use App\Models\User;
use App\Services\Prod\GeneradorLiquidaciones;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->service = app(GeneradorLiquidaciones::class);
});

test('generar reparte el total por porcentaje del empleado', function () {
    $obra = Obra::factory()->create();
    $concepto = Concepto::factory()->create(['obra_id' => $obra->id, 'peso_unitario' => 10.000]);
    $gp = GrupoPrecio::factory()->create(['obra_id' => $obra->id, 'precio_kilo' => 5.0000]);
    GrupoPrecioConcepto::create(['concepto_id' => $concepto->id, 'grupo_precio_id' => $gp->id]);

    $grupo = GrupoTrabajo::factory()->create();
    GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $grupo->id, 'porcentaje' => 70]);
    GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $grupo->id, 'porcentaje' => 30]);

    $destajo = Destajo::factory()->create(['fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-03-08']);
    Registro::factory()->create([
        'fecha' => '2026-03-04',
        'concepto_id' => $concepto->id,
        'grupo_trabajo_id' => $grupo->id,
        'cantidad' => 30,
    ]);

    $this->service->generar($destajo);

    // 30 * 10kg = 300kg * 5 = 1500
    $liq = $destajo->liquidaciones()->with('empleados')->first();
    expect((float) $liq->total_final)->toBe(1500.0);
    expect((float) $liq->empleados->firstWhere('porcentaje', 70.0)->monto_asignado)->toBe(1050.0);
    expect((float) $liq->empleados->firstWhere('porcentaje', 30.0)->monto_asignado)->toBe(450.0);
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
