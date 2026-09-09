<?php

use App\Enums\Alm\ConteoEstatus;
use App\Enums\Alm\ConteoOrigen;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Conteo;
use App\Models\Alm\ConteoPrograma;
use App\Models\Alm\Existencia;
use App\Models\User;
use App\Services\Alm\GeneradorProgramaConteo;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>  $permisos
 */
function usuarioDeConteos(array $permisos = ['ver', 'crear']): User
{
    $user = User::factory()->create();

    $nombres = array_map(fn (string $accion): string => "alm.conteos.{$accion}", $permisos);
    $nombres[] = 'alm.almacenes.ver-todos';

    foreach ($nombres as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($nombres);

    return $user;
}

/** Deja un almacén con N artículos activos con renglón de existencia. */
function almacenConArticulos(int $cuantos): Almacen
{
    $almacen = Almacen::factory()->create();

    Articulo::factory()->count($cuantos)->create()->each(function (Articulo $a) use ($almacen): void {
        Existencia::factory()->create([
            'almacen_id' => $almacen->id,
            'articulo_id' => $a->id,
            'producto_id' => $a->producto_id,
        ]);
    });

    return $almacen;
}

describe('los días de conteo', function () {
    it('caen sólo en los días de la semana elegidos, tantos como hojas hagan falta', function () {
        // 2026-09-07 es lunes. Cuatro hojas sólo lunes y jueves: dos semanas.
        $fechas = GeneradorProgramaConteo::fechasDeConteo(CarbonImmutable::parse('2026-09-07'), [1, 4], 4);

        expect($fechas->map(fn ($f) => $f->toDateString())->all())
            ->toBe(['2026-09-07', '2026-09-10', '2026-09-14', '2026-09-17']);
    });

    it('sin hojas o sin días marcados no cae ninguno', function () {
        expect(GeneradorProgramaConteo::fechasDeConteo(CarbonImmutable::parse('2026-09-07'), [6], 0))->toBeEmpty()
            ->and(GeneradorProgramaConteo::fechasDeConteo(CarbonImmutable::parse('2026-09-07'), [], 3))->toBeEmpty();
    });

    it('la previa dice cuántas hojas, cuándo termina y cuántos días naturales son', function () {
        $almacen = almacenConArticulos(25);

        // 25 a 10 por día son 3 hojas; sólo lunes desde el lunes 7: 7, 14 y 21.
        $previa = GeneradorProgramaConteo::previsualizar($almacen, CarbonImmutable::parse('2026-09-07'), [1], 10);

        expect($previa)->toBe([
            'articulos' => 25,
            'hojas' => 3,
            'fecha_fin' => '2026-09-21',
            'dias_naturales' => 15,
        ]);
    });
});

describe('el reparto', function () {
    it('genera las hojas que hagan falta con los artículos por día pedidos, y todos entran una sola vez', function () {
        $almacen = almacenConArticulos(25);

        $programa = app(GeneradorProgramaConteo::class)->generar(
            $almacen, CarbonImmutable::parse('2026-09-07'), [1, 2, 3, 4, 5], 10,
        );

        // 25 artículos a 10 por día son 3 hojas: lunes, martes y miércoles.
        // La duración la puso el sistema, no el usuario.
        expect($programa->conteos)->toHaveCount(3)
            ->and($programa->articulos_programados)->toBe(25)
            ->and($programa->fecha_fin->toDateString())->toBe('2026-09-09')
            ->and($programa->conteos->map(fn (Conteo $c) => $c->detalles()->count())->all())->toBe([10, 10, 5])
            ->and($programa->conteos->pluck('fecha_programada')->map->toDateString()->all())
            ->toBe(['2026-09-07', '2026-09-08', '2026-09-09']);

        $articulosEnHojas = $programa->conteos->flatMap(fn (Conteo $c) => $c->detalles()->pluck('articulo_id'));

        expect($articulosEnHojas->unique())->toHaveCount(25);
    });

    it('siempre cubre el almacén completo: con pocos días a la semana el programa se alarga', function () {
        $almacen = almacenConArticulos(25);

        // Sólo lunes, a 10 por día: 3 lunes, no 2 hojas y 5 artículos fuera.
        $programa = app(GeneradorProgramaConteo::class)->generar(
            $almacen, CarbonImmutable::parse('2026-09-07'), [1], 10,
        );

        expect($programa->conteos)->toHaveCount(3)
            ->and($programa->articulos_programados)->toBe(25)
            ->and($programa->fecha_fin->toDateString())->toBe('2026-09-21')
            ->and($programa->conteos->pluck('fecha_programada')->map->toDateString()->all())
            ->toBe(['2026-09-07', '2026-09-14', '2026-09-21']);
    });

    it('lo que se da de alta después no entra: el universo se congela al generar', function () {
        $almacen = almacenConArticulos(5);

        $programa = app(GeneradorProgramaConteo::class)->generar(
            $almacen, CarbonImmutable::parse('2026-09-07'), [1, 2, 3, 4, 5], 2,
        );

        $nuevo = Articulo::factory()->create();
        Existencia::factory()->create(['almacen_id' => $almacen->id, 'articulo_id' => $nuevo->id, 'producto_id' => $nuevo->producto_id]);

        $enHojas = $programa->conteos->flatMap(fn (Conteo $c) => $c->detalles()->pluck('articulo_id'));

        expect($programa->fresh()->articulos_programados)->toBe(5)
            ->and($enHojas)->not->toContain($nuevo->id);
    });

    it('reparte al azar: dos programas iguales no salen en el mismo orden', function () {
        $almacen = almacenConArticulos(40);
        $generador = app(GeneradorProgramaConteo::class);

        $ordenes = collect(range(1, 5))->map(fn () => $generador
            ->generar($almacen, CarbonImmutable::parse('2026-09-07'), [1], 40)
            ->conteos->first()->detalles()->pluck('articulo_id')->all());

        expect($ordenes->unique()->count())->toBeGreaterThan(1);
    });

    it('sólo entra lo que tiene existencia en ese almacén y está activo', function () {
        $almacen = almacenConArticulos(3);
        // De otro almacén: no cuenta.
        almacenConArticulos(4);
        // Inactivo en éste: tampoco.
        $inactivo = Articulo::factory()->create(['activo' => false]);
        Existencia::factory()->create(['almacen_id' => $almacen->id, 'articulo_id' => $inactivo->id, 'producto_id' => $inactivo->producto_id]);

        $programa = app(GeneradorProgramaConteo::class)->generar(
            $almacen, CarbonImmutable::parse('2026-09-07'), [1, 2, 3, 4, 5], 50,
        );

        expect($programa->articulos_programados)->toBe(3)
            ->and(GeneradorProgramaConteo::articulosContables($almacen))->toBe(3);
    });

    it('las hojas nacen pendientes, del programa y con folio CIC', function () {
        $almacen = almacenConArticulos(2);

        $programa = app(GeneradorProgramaConteo::class)->generar(
            $almacen, CarbonImmutable::parse('2026-09-07'), [1], 5,
        );

        $hoja = $programa->conteos->first();

        expect($hoja->folio)->toStartWith('CIC-')
            ->and($hoja->estatus)->toBe(ConteoEstatus::Pendiente)
            ->and($hoja->origen)->toBe(ConteoOrigen::Programado)
            ->and($hoja->programa_id)->toBe($programa->id)
            ->and($hoja->detalles()->first()->cantidad_sistema)->toBeNull()
            ->and($hoja->detalles()->first()->cantidad_contada)->toBeNull();
    });

    it('se niega sin días de la semana o si el almacén está vacío', function () {
        $almacen = almacenConArticulos(2);
        $generador = app(GeneradorProgramaConteo::class);

        expect(fn () => $generador->generar($almacen, CarbonImmutable::parse('2026-09-07'), [], 5))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $generador->generar(Almacen::factory()->create(), CarbonImmutable::parse('2026-09-07'), [1], 5))
            ->toThrow(InvalidArgumentException::class)
            ->and(ConteoPrograma::count())->toBe(0);
    });
});

describe('la pantalla', function () {
    it('programa desde el modal y regresa al listado filtrado por el programa', function () {
        $almacen = almacenConArticulos(12);

        $this->actingAs(usuarioDeConteos())
            ->post(route('admin.alm.conteos.programas.store'), [
                'almacen_id' => $almacen->id,
                'fecha_inicio' => '2026-09-07',
                'dias_semana' => [1, 3, 5],
                'articulos_por_dia' => 5,
            ])
            ->assertRedirect(route('admin.alm.conteos.index', ['programa_id' => ConteoPrograma::first()->id]))
            ->assertSessionHas('success');

        // 12 a 5 por día son 3 hojas: lunes 7, miércoles 9 y viernes 11.
        expect(Conteo::count())->toBe(3)
            ->and(ConteoPrograma::sole()->articulos_programados)->toBe(12)
            ->and(ConteoPrograma::sole()->fecha_fin->toDateString())->toBe('2026-09-11');
    });

    it('valida lo que pide el modal', function () {
        $this->actingAs(usuarioDeConteos())
            ->post(route('admin.alm.conteos.programas.store'), [
                'almacen_id' => null,
                'fecha_inicio' => '',
                'dias_semana' => [],
                'articulos_por_dia' => 0,
            ])
            ->assertSessionHasErrors(['almacen_id', 'fecha_inicio', 'dias_semana', 'articulos_por_dia']);
    });

    it('avisa cuando el almacén no tiene nada que contar', function () {
        $almacen = Almacen::factory()->create();

        $this->actingAs(usuarioDeConteos())
            ->post(route('admin.alm.conteos.programas.store'), [
                'almacen_id' => $almacen->id,
                'fecha_inicio' => '2026-09-07',
                'dias_semana' => [6],
                'articulos_por_dia' => 5,
            ])
            ->assertSessionHasErrors(['almacen_id']);

        expect(Conteo::count())->toBe(0);
    });

    it('no deja programar sin permiso', function () {
        $almacen = almacenConArticulos(2);

        $this->actingAs(usuarioDeConteos(['ver']))
            ->post(route('admin.alm.conteos.programas.store'), [
                'almacen_id' => $almacen->id,
                'fecha_inicio' => '2026-09-07',
                'dias_semana' => [1],
                'articulos_por_dia' => 5,
            ])
            ->assertForbidden();
    });

    it('el listado trae las hojas y los programas', function () {
        $almacen = almacenConArticulos(4);
        app(GeneradorProgramaConteo::class)->generar($almacen, CarbonImmutable::today()->subDays(3), [1, 2, 3, 4, 5, 6, 7], 2);

        $this->actingAs(usuarioDeConteos(['ver']))
            ->get(route('admin.alm.conteos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/almacen/conteos/index')
                ->has('conteos.data', 2)
                ->has('programas', 1)
                ->where('almacenes.0.articulos', 4));
    });

    it('la hoja enseña sus renglones en orden y sin saldo del sistema', function () {
        $almacen = almacenConArticulos(3);
        $programa = app(GeneradorProgramaConteo::class)->generar($almacen, CarbonImmutable::today(), [1, 2, 3, 4, 5, 6, 7], 10);
        $hoja = $programa->conteos->first();

        $this->actingAs(usuarioDeConteos(['ver']))
            ->get(route('admin.alm.conteos.show', $hoja))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/almacen/conteos/show')
                ->where('conteo.folio', $hoja->folio)
                ->has('conteo.renglones', 3)
                ->where('conteo.renglones.0.orden', 1)
                ->where('conteo.renglones.0.cantidad_sistema', null));
    });

    it('imprime la hoja en PDF', function () {
        $almacen = almacenConArticulos(2);
        $programa = app(GeneradorProgramaConteo::class)->generar($almacen, CarbonImmutable::today(), [1, 2, 3, 4, 5, 6, 7], 10);

        $this->actingAs(usuarioDeConteos(['ver']))
            ->get(route('admin.alm.conteos.pdf', $programa->conteos->first()))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('no enseña hojas de almacenes que el usuario no ve', function () {
        $almacen = almacenConArticulos(2);
        $programa = app(GeneradorProgramaConteo::class)->generar($almacen, CarbonImmutable::today(), [1, 2, 3, 4, 5, 6, 7], 10);

        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'alm.conteos.ver', 'guard_name' => 'web']);
        $user->givePermissionTo('alm.conteos.ver');

        $this->actingAs($user)
            ->get(route('admin.alm.conteos.show', $programa->conteos->first()))
            ->assertForbidden();
    });
});
