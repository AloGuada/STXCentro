<?php

use App\Enums\Prod\EstadoAsistencia;
use App\Models\Concepto;
use App\Models\Prod\Asistencia;
use App\Models\Prod\Catalogo;
use App\Models\Prod\CategoriaEmpleado;
use App\Models\Prod\ConfiguracionProd;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Registro;
use App\Models\User;
use App\Services\Prod\GeneradorLiquidaciones;
use App\Services\Prod\RepartoDelGrupo;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->user = User::factory()->create();
    Auth::login($this->user);

    ConfiguracionProd::actual()->update(['salario_minimo_diario' => 300]);

    $this->destajo = Destajo::factory()->create([
        'anio' => 2026,
        'semana' => 6,
        'fecha_inicio' => '2026-02-02',
        'fecha_fin' => '2026-02-08',
    ]);

    $this->grupo = GrupoTrabajo::factory()->create(['descripcion' => 'Cuadrilla A']);

    $this->oficial = CategoriaEmpleado::factory()->create(['nombre' => 'Oficial', 'valor' => 2000]);
    $this->ayudante = CategoriaEmpleado::factory()->create(['nombre' => 'Ayudante', 'valor' => 1000]);
});

/** Da de alta un empleado con su categoria y sus dias de asistencia. */
function empleadoCon(CategoriaEmpleado $categoria, int $diasAsistidos, string $nombre): GrupoEmpleado
{
    $empleado = GrupoEmpleado::factory()->create([
        'grupo_trabajo_id' => test()->grupo->id,
        'nombre' => $nombre,
        'categoria_empleado_id' => $categoria->id,
    ]);

    for ($dia = 2; $dia <= 8; $dia++) {
        $indice = $dia - 2;

        Asistencia::factory()->create([
            'destajo_id' => test()->destajo->id,
            'grupo_empleado_id' => $empleado->id,
            'fecha' => sprintf('2026-02-%02d', $dia),
            'estado' => $indice < $diasAsistidos
                ? EstadoAsistencia::Asistencia
                : EstadoAsistencia::Falta,
        ]);
    }

    return $empleado;
}

describe('sueldo base por asistencia', function () {
    test('cada dia pagado vale un salario minimo diario', function () {
        empleadoCon($this->oficial, 7, 'Completo');
        empleadoCon($this->ayudante, 5, 'Con dos faltas');

        $reparto = app(RepartoDelGrupo::class)->calcular($this->destajo, $this->grupo->fresh('empleados'), 0);

        // 7 x 300 = 2100 ; 5 x 300 = 1500
        expect($reparto['empleados'][0]['sueldo_base'])->toBe(2100.0)
            ->and($reparto['empleados'][1]['sueldo_base'])->toBe(1500.0)
            ->and($reparto['total_bases'])->toBe(3600.0);
    });

    test('las vacaciones cuentan como dia pagado y la falta no', function () {
        $empleado = GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => $this->grupo->id,
            'categoria_empleado_id' => $this->oficial->id,
        ]);

        foreach ([
            ['2026-02-02', EstadoAsistencia::Asistencia],
            ['2026-02-03', EstadoAsistencia::Vacaciones],
            ['2026-02-04', EstadoAsistencia::Falta],
            ['2026-02-05', EstadoAsistencia::NoAplica],
        ] as [$fecha, $estado]) {
            Asistencia::factory()->create([
                'destajo_id' => $this->destajo->id,
                'grupo_empleado_id' => $empleado->id,
                'fecha' => $fecha,
                'estado' => $estado,
            ]);
        }

        $reparto = app(RepartoDelGrupo::class)->calcular($this->destajo, $this->grupo->fresh('empleados'), 0);

        // Solo asistencia + vacaciones: 2 x 300
        expect($reparto['empleados'][0]['dias_pagados'])->toBe(2)
            ->and($reparto['empleados'][0]['sueldo_base'])->toBe(600.0);
    });
});

describe('reparto del excedente', function () {
    test('se prorratea por el peso de la categoria', function () {
        empleadoCon($this->oficial, 7, 'Oficial');
        empleadoCon($this->ayudante, 7, 'Ayudante');

        // Bases: 2100 + 2100 = 4200. Total 7200 -> excedente 3000.
        $reparto = app(RepartoDelGrupo::class)->calcular($this->destajo, $this->grupo->fresh('empleados'), 7200);

        // Pesos 2000 y 1000: dos tercios y un tercio de 3000.
        expect($reparto['excedente'])->toBe(3000.0)
            ->and($reparto['empleados'][0]['monto_destajo'])->toBe(2000.0)
            ->and($reparto['empleados'][1]['monto_destajo'])->toBe(1000.0)
            ->and($reparto['empleados'][0]['monto_asignado'])->toBe(4100.0)
            ->and($reparto['empleados'][1]['monto_asignado'])->toBe(3100.0);
    });

    test('el excedente se calcula despues de cubrir todas las bases', function () {
        empleadoCon($this->oficial, 7, 'Uno');
        empleadoCon($this->oficial, 7, 'Dos');

        $reparto = app(RepartoDelGrupo::class)->calcular($this->destajo, $this->grupo->fresh('empleados'), 5000);

        // 5000 - (2100 + 2100) = 800, mitad y mitad por peso igual.
        expect($reparto['excedente'])->toBe(800.0)
            ->and($reparto['empleados'][0]['monto_destajo'])->toBe(400.0);
    });

    test('las faltas tambien recortan lo que se lleva del excedente', function () {
        $completo = empleadoCon($this->oficial, 7, 'Completo');
        empleadoCon($this->oficial, 3, 'Faltista');

        $reparto = app(RepartoDelGrupo::class)->calcular($this->destajo, $this->grupo->fresh('empleados'), 6000);

        // Mismo peso: el excedente se parte igual, pero la base del faltista es menor.
        expect($reparto['empleados'][0]['sueldo_base'])->toBe(2100.0)
            ->and($reparto['empleados'][1]['sueldo_base'])->toBe(900.0)
            ->and($reparto['empleados'][0]['monto_asignado'])
            ->toBeGreaterThan($reparto['empleados'][1]['monto_asignado'])
            ->and($completo->fresh())->not->toBeNull();
    });
});

describe('delta negativo', function () {
    test('si el destajo no cubre las bases cada quien se lleva la suya', function () {
        empleadoCon($this->oficial, 7, 'Uno');
        empleadoCon($this->ayudante, 7, 'Dos');

        // Bases 4200 pero el grupo solo genero 1000.
        $reparto = app(RepartoDelGrupo::class)->calcular($this->destajo, $this->grupo->fresh('empleados'), 1000);

        expect($reparto['excedente'])->toBe(0.0)
            ->and($reparto['empleados'][0]['monto_asignado'])->toBe(2100.0)
            ->and($reparto['empleados'][1]['monto_asignado'])->toBe(2100.0);
    });
});

describe('sin datos', function () {
    test('un empleado sin categoria no participa del excedente pero cobra su base', function () {
        GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => $this->grupo->id,
            'categoria_empleado_id' => null,
        ]);
        $empleado = $this->grupo->empleados()->firstOrFail();

        for ($dia = 2; $dia <= 8; $dia++) {
            Asistencia::factory()->create([
                'destajo_id' => $this->destajo->id,
                'grupo_empleado_id' => $empleado->id,
                'fecha' => sprintf('2026-02-%02d', $dia),
            ]);
        }

        $reparto = app(RepartoDelGrupo::class)->calcular($this->destajo, $this->grupo->fresh('empleados'), 9000);

        expect($reparto['empleados'][0]['sueldo_base'])->toBe(2100.0)
            ->and($reparto['empleados'][0]['monto_destajo'])->toBe(0.0);
    });

    test('sin asistencia capturada no hay sueldo base', function () {
        GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => $this->grupo->id,
            'categoria_empleado_id' => $this->oficial->id,
        ]);

        $reparto = app(RepartoDelGrupo::class)->calcular($this->destajo, $this->grupo->fresh('empleados'), 5000);

        expect($reparto['empleados'][0]['dias_pagados'])->toBe(0)
            ->and($reparto['empleados'][0]['sueldo_base'])->toBe(0.0)
            // Todo el total se vuelve excedente y se le reparte.
            ->and($reparto['empleados'][0]['monto_destajo'])->toBe(5000.0);
    });
});

test('la liquidacion congela el reparto del empleado', function () {
    $catalogo = Catalogo::factory()->create();
    $pieza = Concepto::factory()->create([
        'obra_id' => $catalogo->obra_id,
        'catalogo_id' => $catalogo->id,
        'cantidad' => 100,
        'peso_unitario' => 100,
    ]);
    $grupoPrecio = GrupoPrecio::factory()->create(['obra_id' => $catalogo->obra_id, 'precio_kilo' => 10]);
    GrupoPrecioConcepto::create(['grupo_precio_id' => $grupoPrecio->id, 'concepto_id' => $pieza->id]);

    empleadoCon($this->oficial, 7, 'Oficial');

    Registro::create([
        'fecha' => '2026-02-04',
        'concepto_id' => $pieza->id,
        'grupo_trabajo_id' => $this->grupo->id,
        'cantidad' => 6,
        'porcentaje' => 100,
    ]);

    app(GeneradorLiquidaciones::class)->generar($this->destajo);

    $fila = $this->destajo->liquidaciones()->firstOrFail()->empleados()->firstOrFail();

    // 6 pz x 100 kg x $10 = 6000 ; base 2100 ; excedente 3900
    expect($fila->dias_pagados)->toBe(7)
        ->and($fila->categoria_nombre)->toBe('Oficial')
        ->and($fila->categoria_valor)->toBe(2000)
        ->and((float) $fila->salario_diario)->toBe(300.0)
        ->and((float) $fila->sueldo_base)->toBe(2100.0)
        ->and((float) $fila->monto_destajo)->toBe(3900.0)
        ->and((float) $fila->monto_asignado)->toBe(6000.0);

    // Cambiar la configuracion y la categoria no mueve lo ya liquidado.
    ConfiguracionProd::actual()->update(['salario_minimo_diario' => 999]);
    $this->oficial->update(['valor' => 50, 'nombre' => 'Renombrada']);

    expect((float) $fila->fresh()->salario_diario)->toBe(300.0)
        ->and($fila->fresh()->categoria_nombre)->toBe('Oficial');
});
