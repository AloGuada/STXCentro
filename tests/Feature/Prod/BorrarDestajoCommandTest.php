<?php

use App\Models\Prod\Asistencia;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Liquidacion;
use App\Models\Prod\LiquidacionDetalle;
use App\Models\Prod\LiquidacionEmpleado;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Registro;

function destajoConTodo(): Destajo
{
    $destajo = Destajo::factory()->create([
        'anio' => 2026,
        'semana' => 31,
        'fecha_inicio' => '2026-07-27',
        'fecha_fin' => '2026-08-02',
    ]);

    $grupo = GrupoTrabajo::factory()->create();
    $liquidacion = Liquidacion::factory()->create([
        'destajo_id' => $destajo->id,
        'grupo_trabajo_id' => $grupo->id,
    ]);
    LiquidacionDetalle::factory()->create(['liquidacion_id' => $liquidacion->id]);
    LiquidacionEmpleado::factory()->create(['liquidacion_id' => $liquidacion->id]);

    PagoExtra::factory()->create(['destajo_id' => $destajo->id, 'grupo_trabajo_id' => $grupo->id]);

    Asistencia::factory()->create([
        'destajo_id' => $destajo->id,
        'grupo_empleado_id' => GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $grupo->id])->id,
        'fecha' => '2026-07-28',
    ]);

    Registro::factory()->create(['fecha' => '2026-07-28', 'grupo_trabajo_id' => $grupo->id]);

    return $destajo;
}

test('el dry-run no borra nada', function () {
    destajoConTodo();

    $this->artisan('prod:borrar-destajo', ['destajo' => '2026/31'])
        ->expectsOutputToContain('DRY-RUN')
        ->assertSuccessful();

    expect(Destajo::count())->toBe(1)
        ->and(Liquidacion::count())->toBe(1);
});

test('borra el destajo con liquidaciones, pagos extra y asistencia', function () {
    destajoConTodo();

    $this->artisan('prod:borrar-destajo', ['destajo' => '2026/31', '--force' => true])
        ->assertSuccessful();

    expect(Destajo::count())->toBe(0)
        ->and(Liquidacion::count())->toBe(0)
        ->and(LiquidacionDetalle::count())->toBe(0)
        ->and(LiquidacionEmpleado::count())->toBe(0)
        ->and(PagoExtra::count())->toBe(0)
        ->and(Asistencia::count())->toBe(0);
});

test('sin la bandera conserva los registros de produccion', function () {
    destajoConTodo();

    $this->artisan('prod:borrar-destajo', ['destajo' => '2026/31', '--force' => true])
        ->assertSuccessful();

    expect(Registro::count())->toBe(1);
});

test('con --con-registros tambien borra la captura de la semana', function () {
    $destajo = destajoConTodo();

    // Un registro fuera del rango no se debe tocar.
    Registro::factory()->create([
        'fecha' => '2026-08-10',
        'grupo_trabajo_id' => $destajo->liquidaciones()->first()->grupo_trabajo_id,
    ]);

    $this->artisan('prod:borrar-destajo', ['destajo' => '2026/31', '--force' => true, '--con-registros' => true])
        ->assertSuccessful();

    expect(Registro::count())->toBe(1)
        ->and(Registro::first()->fecha->toDateString())->toBe('2026-08-10');
});

test('acepta el id directo', function () {
    $destajo = destajoConTodo();

    $this->artisan('prod:borrar-destajo', ['destajo' => (string) $destajo->id, '--force' => true])
        ->assertSuccessful();

    expect(Destajo::count())->toBe(0);
});

test('pide confirmacion si el destajo esta cerrado', function () {
    $destajo = destajoConTodo();
    $destajo->update(['cerrado' => true, 'fecha_cierre' => now()]);

    $this->artisan('prod:borrar-destajo', ['destajo' => '2026/31', '--force' => true])
        ->expectsConfirmation('El destajo 2026/31 está CERRADO y su liquidación es definitiva. ¿Borrarlo de todos modos?', 'no')
        ->assertFailed();

    expect(Destajo::count())->toBe(1);
});

test('falla si el destajo no existe', function () {
    $this->artisan('prod:borrar-destajo', ['destajo' => '2026/52'])
        ->expectsOutputToContain('No existe el destajo 2026/52.')
        ->assertFailed();
});
