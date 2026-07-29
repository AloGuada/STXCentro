<?php

use App\Models\Costos\Presupuesto;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'costos.obra-rubros.ver', 'guard_name' => 'web']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('costos.obra-rubros.ver');
});

describe('acceso', function () {
    test('basta cualquier permiso de costos, no el de presupuestos', function () {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'costos.facturas.ver', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('costos.facturas.ver');

        $this->actingAs($user)
            ->get(route('admin.costos.obras-activas.index'))
            ->assertOk();
    });

    test('tambien entra por un rol con permisos de costos', function () {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'costos.pagos.ver', 'guard_name' => 'web']);
        $rol = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'tesoreria', 'guard_name' => 'web']);
        $rol->givePermissionTo('costos.pagos.ver');

        $user = User::factory()->create();
        $user->assignRole($rol);

        $this->actingAs($user)
            ->get(route('admin.costos.obras-activas.index'))
            ->assertOk();
    });

    test('sin ningun permiso de costos sigue bloqueado', function () {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'prod.destajos.ver', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('prod.destajos.ver');

        $this->actingAs($user)
            ->get(route('admin.costos.obras-activas.index'))
            ->assertForbidden();
    });

    test('el indice de presupuestos si sigue pidiendo su permiso', function () {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'costos.facturas.ver', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('costos.facturas.ver');

        $this->actingAs($user)
            ->get(route('admin.costos.presupuestos.index'))
            ->assertForbidden();
    });
});

it('lista solo los presupuestos activos por defecto y cuenta ambos', function () {
    Presupuesto::factory()->paraObra(Obra::factory()->create())->create();
    Presupuesto::factory()->paraObra(Obra::factory()->create())->create();
    Presupuesto::factory()->paraObra(Obra::factory()->create())->cerrado()->create();

    $this->actingAs($this->user)
        ->get(route('admin.costos.obras-activas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/costos/obras-activas/index')
            ->where('estatus', 'activo')
            ->has('presupuestos.data', 2)
            ->where('conteos.activo', 2)
            ->where('conteos.cerrado', 1)
        );
});

it('con estatus=cerrado muestra las cerradas', function () {
    Presupuesto::factory()->paraObra(Obra::factory()->create())->create();
    Presupuesto::factory()->paraObra(Obra::factory()->create())->cerrado()->create();

    $this->actingAs($this->user)
        ->get(route('admin.costos.obras-activas.index', ['estatus' => 'cerrado']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('estatus', 'cerrado')
            ->has('presupuestos.data', 1)
        );
});

it('la busqueda por descripcion es case-insensitive', function () {
    Presupuesto::factory()->paraObra(Obra::factory()->create(['descripcion' => 'Torre NORTE']))->create();
    Presupuesto::factory()->paraObra(Obra::factory()->create(['descripcion' => 'Puente Sur']))->create();

    $this->actingAs($this->user)
        ->get(route('admin.costos.obras-activas.index', ['search' => 'norte']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('presupuestos.data', 1));
});

it('la columna OP usa la op interna si existe, si no la del presupuestable', function () {
    // Con OP interna: se usa esa.
    Presupuesto::factory()
        ->paraObra(Obra::factory()->create(['no' => 'OP-100', 'descripcion' => 'Con override']))
        ->create(['op_interno' => 'OP-INTERNA']);
    // Sin OP interna: cae al `no` del presupuestable (cobranza).
    Presupuesto::factory()
        ->paraObra(Obra::factory()->create(['no' => 'OP-200', 'descripcion' => 'Sin override']))
        ->create(['op_interno' => null]);

    $this->actingAs($this->user)
        ->get(route('admin.costos.obras-activas.index', ['sort_by' => 'descripcion', 'sort_dir' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // 'Con override' < 'Sin override' alfabéticamente.
            ->where('presupuestos.data.0.op', 'OP-INTERNA')
            ->where('presupuestos.data.1.op', 'OP-200')
        );
});

it('permite ordenar por la columna descripcion del presupuestable', function () {
    Presupuesto::factory()->paraObra(Obra::factory()->create(['descripcion' => 'Bravo']))->create();
    Presupuesto::factory()->paraObra(Obra::factory()->create(['descripcion' => 'Alfa']))->create();

    $this->actingAs($this->user)
        ->get(route('admin.costos.obras-activas.index', ['sort_by' => 'descripcion', 'sort_dir' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sortBy', 'descripcion')
            ->where('presupuestos.data.0.descripcion', 'Alfa')
            ->where('presupuestos.data.1.descripcion', 'Bravo')
        );
});
