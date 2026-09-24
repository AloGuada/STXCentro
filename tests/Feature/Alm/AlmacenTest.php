<?php

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Transferencia;
use App\Models\Obra;
use App\Models\User;
use App\Models\Usuario;
use App\Services\Alm\AlmacenLedger;
use App\Services\Alm\RegistradorPiezas;
use App\Services\Alm\RegistradorPrestamos;
use Spatie\Permission\Models\Permission;

/**
 * Usuario de almacén con `ver-todos`, que es el estado normal: la migración se
 * lo dio a todo rol que ya podía ver el catálogo, así que la visibilidad por
 * almacén sólo muerde cuando alguien decide restringir a un almacenista. Para
 * probar ese caso está `usuarioDeUnSoloAlmacen()`.
 *
 * @param  list<string>  $permisos
 */
function usuarioDeAlmacen(array $permisos = ['ver', 'crear', 'editar', 'eliminar']): User
{
    $user = User::factory()->create();

    $nombres = array_map(fn (string $accion): string => "alm.almacenes.{$accion}", $permisos);
    $nombres[] = 'alm.almacenes.ver-todos';

    foreach ($nombres as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($nombres);

    return $user;
}

/**
 * Almacenista restringido: puede operar, pero sólo donde está asignado.
 *
 * @param  list<string>  $permisos
 */
function usuarioDeUnSoloAlmacen(Almacen $almacen, array $permisos = ['ver', 'editar', 'eliminar']): User
{
    $user = User::factory()->create();

    $nombres = array_map(fn (string $accion): string => "alm.almacenes.{$accion}", $permisos);

    foreach ($nombres as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($nombres);
    $almacen->usuarios()->attach($user->id);

    return $user;
}

describe('catalogo de almacenes', function () {
    test('el index lista los almacenes con su obra', function () {
        Almacen::factory()->count(2)->create();
        Almacen::factory()->deObra()->create();

        $this->actingAs(usuarioDeAlmacen(['ver']))
            ->get(route('admin.alm.almacenes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/almacen/almacenes/index')
                ->has('almacenes.data', 3));
    });

    test('se da de alta un almacen central', function () {
        $this->actingAs(usuarioDeAlmacen())
            ->post(route('admin.alm.almacenes.store'), [
                'clave' => 'AG',
                'nombre' => 'Almacén general',
                'obra_id' => null,
                'tipo' => 'insumos',
                'activo' => true,
            ])
            ->assertRedirect(route('admin.alm.almacenes.index'));

        $this->assertDatabaseHas('alm_almacenes', [
            'clave' => 'AG',
            'obra_id' => null,
            'tipo' => 'insumos',
        ]);
    });

    test('la clave se repite entre obras pero no dentro de la misma', function () {
        $obra = Obra::factory()->create();
        $otra = Obra::factory()->create();
        Almacen::factory()->create(['clave' => 'AG', 'obra_id' => $obra->id]);

        // Misma clave en otra obra: es el AG de ese edificio, no un duplicado.
        $this->actingAs(usuarioDeAlmacen())
            ->post(route('admin.alm.almacenes.store'), [
                'clave' => 'AG',
                'nombre' => 'Almacén general',
                'obra_id' => $otra->id,
                'tipo' => 'montaje',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs(usuarioDeAlmacen())
            ->post(route('admin.alm.almacenes.store'), [
                'clave' => 'AG',
                'nombre' => 'Otro general',
                'obra_id' => $obra->id,
                'tipo' => 'montaje',
            ])
            ->assertSessionHasErrors('clave');

        expect(Almacen::count())->toBe(2);
    });

    test('los almacenes de la planta conviven sin obra pero no repiten clave', function () {
        // Los cuatro de la planta van sin obra. Antes esto pasaba: comparar
        // `obra_id = NULL` nunca empata y dejaba entrar la clave repetida.
        foreach (['AG', 'EPP', 'HER', 'REF'] as $clave) {
            $this->actingAs(usuarioDeAlmacen())
                ->post(route('admin.alm.almacenes.store'), [
                    'clave' => $clave,
                    'nombre' => "Central {$clave}",
                    'obra_id' => null,
                    'tipo' => 'insumos',
                ])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs(usuarioDeAlmacen())
            ->post(route('admin.alm.almacenes.store'), [
                'clave' => 'AG',
                'nombre' => 'Otro general',
                'obra_id' => null,
                'tipo' => 'insumos',
            ])
            ->assertSessionHasErrors('clave');

        expect(Almacen::whereNull('obra_id')->count())->toBe(4);
    });

    test('editar un almacen central no choca consigo mismo', function () {
        $almacen = Almacen::factory()->create(['clave' => 'AG', 'obra_id' => null]);
        Almacen::factory()->create(['clave' => 'EPP', 'obra_id' => null]);

        $this->actingAs(usuarioDeAlmacen())
            ->put(route('admin.alm.almacenes.update', $almacen), [
                'clave' => 'AG',
                'nombre' => 'Almacén general de planta',
                'obra_id' => null,
                'tipo' => 'insumos',
            ])
            ->assertSessionHasNoErrors();

        // Pero tomar la clave de otro central sí se rechaza.
        $this->actingAs(usuarioDeAlmacen())
            ->put(route('admin.alm.almacenes.update', $almacen), [
                'clave' => 'EPP',
                'nombre' => 'Almacén general de planta',
                'obra_id' => null,
                'tipo' => 'insumos',
            ])
            ->assertSessionHasErrors('clave');
    });

    test('el responsable es opcional y se guarda', function () {
        $responsable = Usuario::factory()->create();

        $this->actingAs(usuarioDeAlmacen())
            ->post(route('admin.alm.almacenes.store'), [
                'clave' => 'FAK',
                'nombre' => 'Fachadas',
                'tipo' => 'montaje',
                'responsable_id' => $responsable->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('alm_almacenes', [
            'clave' => 'FAK',
            'responsable_id' => $responsable->id,
        ]);
    });

    test('el tipo tiene que ser uno del catalogo', function () {
        $this->actingAs(usuarioDeAlmacen())
            ->post(route('admin.alm.almacenes.store'), [
                'clave' => 'X',
                'nombre' => 'Invalido',
                'tipo' => 'bodega',
            ])
            ->assertSessionHasErrors('tipo');
    });

    test('se edita y se elimina', function () {
        $almacen = Almacen::factory()->create(['clave' => 'A', 'nombre' => 'Viejo']);

        // `activo` ya no se toca desde la edición: sólo por desactivar/reactivar.
        $this->actingAs(usuarioDeAlmacen())
            ->put(route('admin.alm.almacenes.update', $almacen), [
                'clave' => 'A',
                'nombre' => 'Nuevo',
                'tipo' => 'herramienta',
                'activo' => false,
            ])
            ->assertRedirect(route('admin.alm.almacenes.index'));

        $this->assertDatabaseHas('alm_almacenes', [
            'id' => $almacen->id,
            'nombre' => 'Nuevo',
            'tipo' => 'herramienta',
            'activo' => true,
        ]);

        $this->actingAs(usuarioDeAlmacen())
            ->delete(route('admin.alm.almacenes.destroy', $almacen))
            ->assertRedirect(route('admin.alm.almacenes.index'));

        $this->assertDatabaseMissing('alm_almacenes', ['id' => $almacen->id]);
    });

    test('borrar la obra deja el almacen sin obra, no lo borra', function () {
        $obra = Obra::factory()->create();
        $almacen = Almacen::factory()->create(['obra_id' => $obra->id]);

        $obra->delete();

        expect(Almacen::whereKey($almacen->id)->value('obra_id'))->toBeNull();
    });
});

describe('desactivar un almacen', function () {
    test('un almacen vacio se desactiva y se vuelve a activar', function () {
        $almacen = Almacen::factory()->create(['clave' => 'CONST']);
        $user = usuarioDeAlmacen();

        $this->actingAs($user)
            ->from(route('admin.alm.almacenes.edit', $almacen))
            ->patch(route('admin.alm.almacenes.toggle', $almacen))
            ->assertRedirect(route('admin.alm.almacenes.edit', $almacen))
            ->assertSessionHas('success');

        expect($almacen->fresh()->activo)->toBeFalse();

        $this->actingAs($user)
            ->patch(route('admin.alm.almacenes.toggle', $almacen))
            ->assertSessionHas('success');

        expect($almacen->fresh()->activo)->toBeTrue();
    });

    test('con saldo se niega: el material no se queda en un almacen que nadie ve', function () {
        $almacen = Almacen::factory()->create();
        app(AlmacenLedger::class)->registrarPorArticulo(
            almacenId: $almacen->id,
            articuloId: Articulo::factory()->create()->id,
            tipo: MovimientoTipo::Entrada,
            cantidad: 5,
            costoUnitario: 10,
        );

        $this->actingAs(usuarioDeAlmacen())
            ->patch(route('admin.alm.almacenes.toggle', $almacen))
            ->assertSessionHasErrors('activo');

        expect($almacen->fresh()->activo)->toBeTrue();
    });

    test('con una transferencia en transito se niega aunque ya no tenga saldo', function () {
        $almacen = Almacen::factory()->create();
        Transferencia::factory()->create(['almacen_origen_id' => $almacen->id, 'folio' => 'TRA-2609-01']);

        $this->actingAs(usuarioDeAlmacen())
            ->patch(route('admin.alm.almacenes.toggle', $almacen))
            ->assertSessionHasErrors(['activo' => 'No se puede desactivar: hay transferencias en tránsito (TRA-2609-01). Confírmalas primero.']);

        expect($almacen->fresh()->activo)->toBeTrue();
    });

    test('la edicion manda lo que impide desactivarlo para explicarlo en el modal', function () {
        $almacen = Almacen::factory()->create();
        $piezas = app(RegistradorPiezas::class);
        $pulidora = Articulo::factory()->porPieza()->create();
        [$pieza] = $piezas->alta($pulidora, $almacen, [['no_serie' => 'PUL-1']]);
        $prestamo = app(RegistradorPrestamos::class)->prestar($almacen, [
            'responsable_id' => supervisorDeAlmacen()->id,
            'fecha_salida' => today()->toDateString(),
        ], [['articulo_id' => $pulidora->id, 'activo_id' => $pieza->id]]);

        $this->actingAs(usuarioDeAlmacen())
            ->get(route('admin.alm.almacenes.edit', $almacen))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('bloqueos.articulos_con_saldo', 1)
                ->where('bloqueos.prestamos_abiertos', [$prestamo->folio])
                ->where('bloqueos.transferencias_en_transito', []));
    });

    test('quien solo puede ver no lo desactiva', function () {
        $almacen = Almacen::factory()->create();

        $this->actingAs(usuarioDeAlmacen(['ver']))
            ->patch(route('admin.alm.almacenes.toggle', $almacen))
            ->assertForbidden();

        expect($almacen->fresh()->activo)->toBeTrue();
    });

    test('un almacenista no desactiva un almacen que no es suyo', function () {
        $suyo = Almacen::factory()->create();
        $ajeno = Almacen::factory()->create();

        $this->actingAs(usuarioDeUnSoloAlmacen($suyo))
            ->patch(route('admin.alm.almacenes.toggle', $ajeno))
            ->assertForbidden();

        expect($ajeno->fresh()->activo)->toBeTrue();
    });
});

describe('permisos del catalogo de almacenes', function () {
    test('sin permiso de ver no entra al index', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.alm.almacenes.index'))
            ->assertForbidden();
    });

    test('quien solo puede ver no puede crear ni eliminar', function () {
        $almacen = Almacen::factory()->create();
        $user = usuarioDeAlmacen(['ver']);

        $this->actingAs($user)
            ->post(route('admin.alm.almacenes.store'), [
                'clave' => 'Z',
                'nombre' => 'No debería',
                'tipo' => 'insumos',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('admin.alm.almacenes.destroy', $almacen))
            ->assertForbidden();
    });

    test('sin ver-todos solo se listan los almacenes asignados', function () {
        $suyo = Almacen::factory()->create(['clave' => 'AG']);
        Almacen::factory()->create(['clave' => 'FAK']);
        Almacen::factory()->deObra()->create();

        $this->actingAs(usuarioDeUnSoloAlmacen($suyo, ['ver']))
            ->get(route('admin.alm.almacenes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('almacenes.data', 1)
                ->where('almacenes.data.0.clave', 'AG'));
    });

    test('el responsable ve su almacen aunque no este en la lista de asignados', function () {
        $usuario = Usuario::factory()->create();
        $suyo = Almacen::factory()->create(['responsable_id' => $usuario->id]);
        Almacen::factory()->create();

        $almacenista = usuarioDeUnSoloAlmacen($suyo, ['ver']);
        $suyo->usuarios()->detach($almacenista->id);
        $suyo->update(['responsable_id' => $almacenista->id]);

        $this->actingAs($almacenista)
            ->get(route('admin.alm.almacenes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('almacenes.data', 1));
    });

    test('no se edita ni se borra un almacen ajeno', function () {
        $suyo = Almacen::factory()->create();
        $ajeno = Almacen::factory()->create();
        $almacenista = usuarioDeUnSoloAlmacen($suyo);

        $this->actingAs($almacenista)
            ->get(route('admin.alm.almacenes.edit', $ajeno))
            ->assertForbidden();

        $this->actingAs($almacenista)
            ->put(route('admin.alm.almacenes.update', $ajeno), [
                'clave' => 'ZZ',
                'nombre' => 'No debería',
                'tipo' => 'insumos',
            ])
            ->assertForbidden();

        $this->actingAs($almacenista)
            ->delete(route('admin.alm.almacenes.destroy', $ajeno))
            ->assertForbidden();

        $this->assertDatabaseHas('alm_almacenes', ['id' => $ajeno->id]);
    });

    /**
     * Un permiso nuevo se crea en dos lados: la migración `firstOrCreate` (para
     * las bases que ya existen) y `groupedPermissions()` (para las nuevas).
     * Olvidar el segundo deja el permiso vivo pero fuera del seeder, y la
     * diferencia sólo se nota cuando alguien levanta un ambiente desde cero.
     */
    test('el seeder de permisos incluye todos los del modulo', function () {
        $delSeeder = Database\Seeders\RolesAndPermissionsSeeder::groupedPermissions()['alm'];
        $creadosPorMigracion = Spatie\Permission\Models\Permission::where('name', 'like', 'alm.%')
            ->pluck('name')
            ->all();

        expect($creadosPorMigracion)->not->toBeEmpty()
            ->and(array_diff($creadosPorMigracion, $delSeeder))->toBeEmpty()
            ->and($delSeeder)->toContain('alm.almacenes.ver', 'alm.kardex.ver', 'alm.pedidos.aprobar');
    });
});
