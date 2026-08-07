<?php

use App\Models\Alm\Almacen;
use App\Models\Obra;
use App\Models\User;
use App\Models\Usuario;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>  $permisos
 */
function usuarioDeAlmacen(array $permisos = ['ver', 'crear', 'editar', 'eliminar']): User
{
    $user = User::factory()->create();

    $nombres = array_map(fn (string $accion): string => "alm.almacenes.{$accion}", $permisos);

    foreach ($nombres as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($nombres);

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
            'activo' => false,
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

    test('el seeder de permisos incluye los del modulo', function () {
        $delSeeder = Database\Seeders\RolesAndPermissionsSeeder::groupedPermissions()['alm'];

        expect($delSeeder)->toEqualCanonicalizing([
            'alm.almacenes.ver',
            'alm.almacenes.crear',
            'alm.almacenes.editar',
            'alm.almacenes.eliminar',
        ]);
    });
});
