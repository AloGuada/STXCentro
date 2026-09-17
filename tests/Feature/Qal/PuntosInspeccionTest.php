<?php

use App\Enums\Qal\AmbitoPunto;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\ResultadoPunto;
use App\Enums\Qal\Subetapa;
use App\Enums\Qal\SubtipoPrimera;
use App\Models\Qal\PuntoInspeccion;
use Database\Seeders\QalPuntosInspeccionSeeder;

/**
 * El catálogo de puntos de inspección.
 *
 * Cada casilla del formulario viejo era una columna; aquí es una fila con la
 * misma clave. Se comprueba que el catálogo trae lo que pide cada formulario y
 * que cada respuesta sabe si es cumplimiento.
 */
beforeEach(fn () => $this->seed(QalPuntosInspeccionSeeder::class));

test('el seeder siembra las tres fases y el mapeo, y correrlo de nuevo no duplica', function () {
    $total = PuntoInspeccion::count();

    $this->seed(QalPuntosInspeccionSeeder::class);

    expect(PuntoInspeccion::count())->toBe($total)
        ->and(PuntoInspeccion::query()->where('ambito', AmbitoPunto::Junta->value)->count())->toBe(18)
        ->and(PuntoInspeccion::query()->distinct()->pluck('fase')->map->value->sort()->values()->all())
        ->toBe(['1ª', '2ª', '3ª']);
});

test('resembrar no reactiva un punto que alguien desactivo', function () {
    PuntoInspeccion::query()->where('clave', 'p1_defl')->update(['activo' => false]);

    $this->seed(QalPuntosInspeccionSeeder::class);

    expect(PuntoInspeccion::firstWhere('clave', 'p1_defl')->activo)->toBeFalse();
});

test('los puntos de junta solo aparecen en segunda soldado', function () {
    $juntas = PuntoInspeccion::query()->where('ambito', AmbitoPunto::Junta->value)->get();

    expect($juntas->every(fn (PuntoInspeccion $p) => $p->fase === FaseTransformacion::Segunda && $p->subetapa === Subetapa::Soldado))
        ->toBeTrue()
        ->and(PuntoInspeccion::query()->paraFormulario(FaseTransformacion::Segunda, Subetapa::Soldado)->pluck('clave'))
        ->not->toContain('m_poros');
});

test('la placa de primera trae los comunes y los suyos pero no los del perfil', function () {
    $claves = PuntoInspeccion::query()
        ->paraFormulario(FaseTransformacion::Primera, subtipo: SubtipoPrimera::Placa)
        ->pluck('clave');

    expect($claves)->toContain('p1_dim', 'p1_limpieza', 'p1_edoinsp')
        ->not->toContain('p1_defl', 'p1_empates');
});

test('armado trae la preparacion de juntas y no los puntos de soldado', function () {
    $claves = PuntoInspeccion::query()
        ->paraFormulario(FaseTransformacion::Segunda, Subetapa::ArmadoVestido)
        ->pluck('clave');

    expect($claves)->toContain('p2_bisel', 'p2_faltavest', 'p2_long')
        ->not->toContain('p2_elem', 'p2_precal');
});

/**
 * «Fuera de tol.», «Incorrecta» y «Con defecto» se escriben distinto y
 * significan lo mismo. El tipo de desviación sólo describe: no es resultado.
 */
test('cada respuesta sabe si es cumplimiento, defecto o no aplica', function () {
    $posicion = PuntoInspeccion::firstWhere('clave', 'p1_posbar');
    $tipoDesviacion = PuntoInspeccion::firstWhere('clave', 'p2_desvtipo');

    expect($posicion->resultadoDe('OK'))->toBe(ResultadoPunto::Ok)
        ->and($posicion->resultadoDe('Incorrecta'))->toBe(ResultadoPunto::NoOk)
        ->and($posicion->resultadoDe('n/a'))->toBe(ResultadoPunto::NoAplica)
        ->and($posicion->admite('Chueca'))->toBeFalse()
        ->and($tipoDesviacion->admite('Torsión'))->toBeTrue()
        ->and($tipoDesviacion->resultadoDe('Torsión'))->toBeNull();
});

test('los contadores solo admiten enteros y los numeros cualquier medida', function () {
    $faltaVestido = PuntoInspeccion::firstWhere('clave', 'p2_faltavest');

    expect($faltaVestido->admite('2'))->toBeTrue()
        ->and($faltaVestido->admite('1.5'))->toBeFalse()
        ->and(PuntoInspeccion::firstWhere('clave', 'p1_empates')->admite('3.5'))->toBeTrue();
});
