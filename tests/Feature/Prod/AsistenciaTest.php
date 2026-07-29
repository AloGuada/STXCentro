<?php

use App\Enums\Prod\EstadoAsistencia;
use App\Models\Concepto;
use App\Models\Prod\Asistencia;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Registro;
use App\Models\User;
use App\Services\Prod\AsistenciaDelDestajo;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->destajo = Destajo::factory()->create([
        'anio' => 2026,
        'semana' => 6,
        'fecha_inicio' => '2026-02-02',
        'fecha_fin' => '2026-02-08',
    ]);
    $this->grupo = GrupoTrabajo::factory()->create(['descripcion' => 'Cuadrilla A']);
    $this->empleado = GrupoEmpleado::factory()->create([
        'grupo_trabajo_id' => $this->grupo->id,
        'nombre' => 'Juan Pérez',
    ]);

    $catalogo = Catalogo::factory()->create();
    $this->pieza = Concepto::factory()->create([
        'obra_id' => $catalogo->obra_id,
        'catalogo_id' => $catalogo->id,
        'cantidad' => 100,
    ]);

    // El grupo participa en el destajo, asi que debe capturar asistencia.
    Registro::factory()->create([
        'concepto_id' => $this->pieza->id,
        'grupo_trabajo_id' => $this->grupo->id,
        'fecha' => '2026-02-03',
        'cantidad' => 5,
    ]);
});

/** Guarda la semana completa del empleado con un estado dado. */
function guardarSemana(string $estado = 'asistencia'): array
{
    $marcas = [];
    foreach (['02', '03', '04', '05', '06', '07', '08'] as $dia) {
        $marcas[] = [
            'grupo_empleado_id' => test()->empleado->id,
            'fecha' => "2026-02-{$dia}",
            'estado' => $estado,
        ];
    }

    return $marcas;
}

describe('pantalla de asistencia', function () {
    test('muestra los grupos que participan, los dias y lo guardado', function () {
        Asistencia::factory()->create([
            'destajo_id' => $this->destajo->id,
            'grupo_empleado_id' => $this->empleado->id,
            'fecha' => '2026-02-03',
            'estado' => EstadoAsistencia::Falta,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.asistencia', $this->destajo));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/destajos/asistencia')
            ->has('grupos', 1)
            ->has('dias', 7)
            ->where('marcas.'.$this->empleado->id.':2026-02-03', 'falta')
        );
    });

    test('no incluye grupos sin actividad en la semana', function () {
        GrupoTrabajo::factory()->create(['descripcion' => 'Cuadrilla Z']);

        $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.asistencia', $this->destajo))
            ->assertInertia(fn ($page) => $page->has('grupos', 1));
    });
});

describe('guardado de asistencia', function () {
    test('guarda la cuadricula completa', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.asistencia.store', $this->destajo), [
                'marcas' => guardarSemana(),
            ])
            ->assertSessionHasNoErrors();

        expect(Asistencia::where('destajo_id', $this->destajo->id)->count())->toBe(7);
    });

    test('re-guardar actualiza en vez de duplicar', function () {
        $marcas = guardarSemana();

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.asistencia.store', $this->destajo), ['marcas' => $marcas])
            ->assertSessionHasNoErrors();

        $marcas[0]['estado'] = 'falta';

        // Sin assertRedirect, un choque contra el unique pasaria como 500 mudo.
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.asistencia.store', $this->destajo), ['marcas' => $marcas])
            ->assertRedirect();

        expect(Asistencia::where('destajo_id', $this->destajo->id)->count())->toBe(7)
            ->and(Asistencia::where('grupo_empleado_id', $this->empleado->id)
                ->whereDate('fecha', '2026-02-02')->firstOrFail()->estado)
            ->toBe(EstadoAsistencia::Falta);
    });

    test('ignora fechas fuera del periodo del destajo', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.asistencia.store', $this->destajo), [
                'marcas' => [[
                    'grupo_empleado_id' => $this->empleado->id,
                    'fecha' => '2026-03-15',
                    'estado' => 'asistencia',
                ]],
            ])
            ->assertSessionHasErrors('marcas');

        expect(Asistencia::count())->toBe(0);
    });

    test('rechaza estados invalidos', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.asistencia.store', $this->destajo), [
                'marcas' => [[
                    'grupo_empleado_id' => $this->empleado->id,
                    'fecha' => '2026-02-03',
                    'estado' => 'inventado',
                ]],
            ])
            ->assertSessionHasErrors('marcas.0.estado');
    });

    test('no se puede modificar la asistencia de un destajo cerrado', function () {
        $this->destajo->update(['cerrado' => true]);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.asistencia.store', $this->destajo), [
                'marcas' => guardarSemana(),
            ])
            ->assertSessionHasErrors('error');
    });
});

describe('bloqueo del cierre', function () {
    test('no deja cerrar el destajo sin asistencia', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.cerrar', $this->destajo));

        $response->assertSessionHasErrors('error');
        expect($this->destajo->fresh()->cerrado)->toBeFalse();
    });

    test('deja cerrar cuando la asistencia esta completa', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.asistencia.store', $this->destajo), [
                'marcas' => guardarSemana(),
            ]);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.cerrar', $this->destajo))
            ->assertSessionHasNoErrors();

        expect($this->destajo->fresh()->cerrado)->toBeTrue();
    });

    test('un empleado nuevo sin asistencia vuelve a bloquear el cierre', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.asistencia.store', $this->destajo), [
                'marcas' => guardarSemana(),
            ]);

        GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => $this->grupo->id,
            'nombre' => 'Nuevo Ingreso',
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.cerrar', $this->destajo))
            ->assertSessionHasErrors('error');
    });

    test('el destajo avisa a quien le falta asistencia', function () {
        $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.show', $this->destajo))
            ->assertInertia(fn ($page) => $page
                ->has('asistenciaFaltante', 1)
                ->where('asistenciaFaltante.0.grupo', 'Cuadrilla A')
                ->where('asistenciaFaltante.0.empleados.0', 'Juan Pérez')
            );
    });

    test('el aviso desaparece al completar la captura', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.asistencia.store', $this->destajo), [
                'marcas' => guardarSemana(),
            ]);

        expect(app(AsistenciaDelDestajo::class)->estaCompleta($this->destajo))->toBeTrue();
    });
});

describe('estados de asistencia', function () {
    test('asistencia y vacaciones cuentan como dia pagado', function () {
        expect(EstadoAsistencia::Asistencia->cuentaComoPagado())->toBeTrue()
            ->and(EstadoAsistencia::Vacaciones->cuentaComoPagado())->toBeTrue()
            ->and(EstadoAsistencia::Falta->cuentaComoPagado())->toBeFalse()
            ->and(EstadoAsistencia::NoAplica->cuentaComoPagado())->toBeFalse();
    });
});
