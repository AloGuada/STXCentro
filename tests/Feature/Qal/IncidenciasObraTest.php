<?php

use App\Enums\Qal\AreaIncidencia;
use App\Enums\Qal\DepartamentoIncidencia;
use App\Models\Qal\Obra;
use App\Models\Qal\ObraIncidencia;
use App\Models\Qal\ObraMontaje;
use App\Models\User;
use App\Services\Qal\EstadisticaIncidencias;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

/**
 * Incidencias en obra.
 *
 * Lo que se comprueba aquí no es el CRUD, sino las cuatro reglas por las que
 * este módulo existe en vez del Excel que sustituye:
 *
 *  1. El avance de montaje y las incidencias son hechos de grano distinto:
 *     añadir la tercera incidencia de la semana no toca el denominador.
 *  2. «Sin incidencias» es un dato de la semana, no una incidencia con cero
 *     piezas ni un renglón en blanco.
 *  3. Borrar el avance no se lleva las incidencias por delante; las deja sin
 *     denominador, y eso se dice, no se calcula.
 *  4. El corte del reporte semanal: taller contra montaje por área, pintura
 *     por departamento.
 */
function usuarioIncidencias(array $permisos): User
{
    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

/**
 * @return array<string, mixed>
 */
function incidenciaValida(array $cambios = []): array
{
    return [
        'anio' => 2026,
        'semana' => 33,
        'fecha' => '2026-08-12',
        'area' => AreaIncidencia::Montaje->value,
        'departamento' => DepartamentoIncidencia::Construccion->value,
        'pz_defecto' => 3,
        'folio' => 'NC-0042',
        'descripcion' => 'Barrenos desfasados en el empalme',
        ...$cambios,
    ];
}

test('la portada abre con su permiso y queda cerrada sin el', function () {
    $this->actingAs(usuarioIncidencias(['qal.incidencias.ver']))
        ->get(route('admin.qal.incidencias.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/calidad/incidencias/index'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.qal.incidencias.index'))
        ->assertForbidden();
});

test('consultar no alcanza para capturar', function () {
    $obra = Obra::factory()->create();

    $this->actingAs(usuarioIncidencias(['qal.incidencias.ver']))
        ->post(route('admin.qal.incidencias.store', $obra), incidenciaValida())
        ->assertForbidden();

    expect(ObraIncidencia::count())->toBe(0);
});

test('capturar no alcanza para borrar el historial', function () {
    $obra = Obra::factory()->create();
    $incidencia = ObraIncidencia::factory()->create(['qal_obra_id' => $obra->id]);

    $this->actingAs(usuarioIncidencias(['qal.incidencias.capturar']))
        ->delete(route('admin.qal.incidencias.destroy', [$obra, $incidencia]))
        ->assertForbidden();

    expect(ObraIncidencia::count())->toBe(1);
});

test('el avance de montaje es una cifra por semana y se sobreescribe', function () {
    $obra = Obra::factory()->create();
    $usuario = usuarioIncidencias(['qal.incidencias.capturar', 'qal.incidencias.ver']);

    $this->actingAs($usuario)
        ->post(route('admin.qal.incidencias.montaje', $obra), [
            'anio' => 2026, 'semana' => 33, 'pz_montadas' => 120,
        ])->assertRedirect();

    $this->actingAs($usuario)
        ->post(route('admin.qal.incidencias.montaje', $obra), [
            'anio' => 2026, 'semana' => 33, 'pz_montadas' => 140, 'notas' => 'se recontó',
        ])->assertRedirect();

    expect(ObraMontaje::count())->toBe(1)
        ->and(ObraMontaje::first()->pz_montadas)->toBe(140);
});

test('anadir tres incidencias no toca el denominador de la semana', function () {
    $obra = Obra::factory()->create();
    $usuario = usuarioIncidencias(['qal.incidencias.capturar']);

    ObraMontaje::factory()->enLaSemana(2026, 33)->create([
        'qal_obra_id' => $obra->id, 'pz_montadas' => 200,
    ]);

    foreach ([2, 5, 1] as $piezas) {
        $this->actingAs($usuario)
            ->post(route('admin.qal.incidencias.store', $obra), incidenciaValida(['pz_defecto' => $piezas]))
            ->assertRedirect();
    }

    // El problema del Excel: allá las tres incidencias necesitaban tres filas y
    // el denominador se repetía en cada una. Aquí sigue habiendo una sola.
    expect(ObraMontaje::where('qal_obra_id', $obra->id)->count())->toBe(1)
        ->and(ObraMontaje::first()->pz_montadas)->toBe(200)
        ->and(ObraIncidencia::sum('pz_defecto'))->toBe(8);
});

test('una incidencia sin folio ni descripcion no se guarda', function () {
    $obra = Obra::factory()->create();

    $this->actingAs(usuarioIncidencias(['qal.incidencias.capturar']))
        ->post(route('admin.qal.incidencias.store', $obra), incidenciaValida([
            'folio' => null, 'descripcion' => null,
        ]))
        ->assertSessionHasErrors(['folio', 'descripcion']);

    expect(ObraIncidencia::count())->toBe(0);
});

test('una incidencia que no afecta a ninguna pieza no es una incidencia', function () {
    $obra = Obra::factory()->create();

    $this->actingAs(usuarioIncidencias(['qal.incidencias.capturar']))
        ->post(route('admin.qal.incidencias.store', $obra), incidenciaValida(['pz_defecto' => 0]))
        ->assertSessionHasErrors('pz_defecto');
});

test('sin incidencias es una marca de la semana, no una incidencia en cero', function () {
    $obra = Obra::factory()->create();

    $this->actingAs(usuarioIncidencias(['qal.incidencias.capturar']))
        ->post(route('admin.qal.incidencias.sin-incidencias', $obra), [
            'anio' => 2026, 'semana' => 33, 'sin_incidencias' => true,
        ])->assertRedirect();

    expect(ObraIncidencia::count())->toBe(0)
        ->and(ObraMontaje::first()->sin_incidencias)->toBeTrue()
        // La cifra de avance sigue faltando: declarar la semana limpia no es
        // declarar que no se montó nada.
        ->and(ObraMontaje::first()->pz_montadas)->toBeNull();
});

test('registrar un hallazgo levanta la marca de semana limpia', function () {
    $obra = Obra::factory()->create();
    ObraMontaje::factory()->enLaSemana(2026, 33)->revisadaSinIncidencias()
        ->create(['qal_obra_id' => $obra->id]);

    $this->actingAs(usuarioIncidencias(['qal.incidencias.capturar']))
        ->post(route('admin.qal.incidencias.store', $obra), incidenciaValida())
        ->assertRedirect();

    expect(ObraMontaje::first()->sin_incidencias)->toBeFalse();
});

test('borrar el avance conserva las incidencias de esa semana', function () {
    $obra = Obra::factory()->create();
    $montaje = ObraMontaje::factory()->enLaSemana(2026, 33)->create(['qal_obra_id' => $obra->id]);
    ObraIncidencia::factory()->enLaSemana(2026, 33)->create(['qal_obra_id' => $obra->id]);

    $this->actingAs(usuarioIncidencias(['qal.incidencias.eliminar']))
        ->delete(route('admin.qal.incidencias.montaje.destroy', [$obra, $montaje]))
        ->assertRedirect();

    expect(ObraMontaje::count())->toBe(0)
        ->and(ObraIncidencia::count())->toBe(1);
});

test('el estado sale de la fecha de cierre y el boton lo alterna', function () {
    $obra = Obra::factory()->create();
    $incidencia = ObraIncidencia::factory()->create(['qal_obra_id' => $obra->id]);
    $usuario = usuarioIncidencias(['qal.incidencias.capturar']);

    expect($incidencia->abierta)->toBeTrue();

    $this->actingAs($usuario)
        ->patch(route('admin.qal.incidencias.estado', [$obra, $incidencia]))
        ->assertRedirect();

    expect($incidencia->fresh()->cerrada_en)->not->toBeNull()
        ->and($incidencia->fresh()->abierta)->toBeFalse();

    $this->actingAs($usuario)
        ->patch(route('admin.qal.incidencias.estado', [$obra, $incidencia]))
        ->assertRedirect();

    expect($incidencia->fresh()->cerrada_en)->toBeNull();
});

test('una incidencia de otra obra no se toca desde la url de esta', function () {
    $obra = Obra::factory()->create();
    $ajena = ObraIncidencia::factory()->create(['qal_obra_id' => Obra::factory()->create()->id]);

    $this->actingAs(usuarioIncidencias(['qal.incidencias.eliminar']))
        ->delete(route('admin.qal.incidencias.destroy', [$obra, $ajena]))
        ->assertNotFound();

    expect(ObraIncidencia::count())->toBe(1);
});

test('la semana sin piezas montadas no da porcentaje, dice que falta la base', function () {
    $obra = Obra::factory()->create();
    ObraIncidencia::factory()->enLaSemana(2026, 33)->create([
        'qal_obra_id' => $obra->id, 'pz_defecto' => 4,
    ]);

    $this->actingAs(usuarioIncidencias(['qal.incidencias.ver']))
        ->get(route('admin.qal.incidencias.show', [$obra, 'anio' => 2026, 'semana' => 33]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/calidad/incidencias/show')
            ->where('historial.0.pz_defecto', 4)
            ->where('historial.0.tasa', null));
});

test('el reporte semanal parte taller de montaje por area y pintura por departamento', function () {
    $obra = Obra::factory()->conNumero('T4 CANCUN')->create(['pz_total' => 500]);

    ObraMontaje::factory()->enLaSemana(2026, 32)->create(['qal_obra_id' => $obra->id, 'pz_montadas' => 100]);
    ObraMontaje::factory()->enLaSemana(2026, 33)->create(['qal_obra_id' => $obra->id, 'pz_montadas' => 60]);
    // Semana posterior al corte: no debe entrar en el acumulado.
    ObraMontaje::factory()->enLaSemana(2026, 34)->create(['qal_obra_id' => $obra->id, 'pz_montadas' => 999]);

    ObraIncidencia::factory()->enLaSemana(2026, 32)
        ->de(AreaIncidencia::Taller, DepartamentoIncidencia::SegundaTransformacion)
        ->create(['qal_obra_id' => $obra->id, 'pz_defecto' => 5]);
    ObraIncidencia::factory()->enLaSemana(2026, 33)
        ->de(AreaIncidencia::TallerPintura, DepartamentoIncidencia::PinturaTaller)
        ->create(['qal_obra_id' => $obra->id, 'pz_defecto' => 2]);
    ObraIncidencia::factory()->enLaSemana(2026, 33)
        ->de(AreaIncidencia::Montaje, DepartamentoIncidencia::PinturaObra)
        ->create(['qal_obra_id' => $obra->id, 'pz_defecto' => 3]);

    $hojas = app(EstadisticaIncidencias::class)->hojasDelReporteSemanal(2026, 33);

    // Hoja 4: el taller de pintura cuenta como taller, porque también salió de
    // la nave. 5 + 2 contra las 3 aparecidas en sitio.
    expect($hojas['montaje'][0])
        ->toMatchArray(['obra' => 'T4 CANCUN', 'montadas' => 160, 'totales' => 500, 'a' => 7, 'b' => 3])
        ->and($hojas['montaje'][0]['aSemana'])->toBe(2)
        ->and($hojas['montaje'][0]['bSemana'])->toBe(3);

    // Hoja 5: sólo los dos departamentos de pintura, partidos taller / obra.
    expect($hojas['pintura'][0])->toMatchArray(['a' => 2, 'b' => 3])
        ->and($hojas['montadas_semana'])->toBe(60);
});

test('el mes de una semana es el de su jueves', function () {
    $obra = Obra::factory()->create();

    // La semana 5 de 2026 va del lunes 26 de enero al domingo 1 de febrero: su
    // jueves cae en enero, así que la semana entera cuenta en enero y no se
    // parte entre dos meses.
    ObraIncidencia::factory()->enLaSemana(2026, 5)->create([
        'qal_obra_id' => $obra->id, 'pz_defecto' => 4,
    ]);

    expect(Carbon::now()->setISODate(2026, 5, 4)->month)->toBe(1);

    $this->actingAs(usuarioIncidencias(['qal.incidencias.ver']))
        ->get(route('admin.qal.incidencias.index', ['anio' => 2026]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('graficas.porMes.0.mes', 1));
});
