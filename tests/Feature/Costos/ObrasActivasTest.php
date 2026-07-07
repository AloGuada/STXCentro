<?php

use App\Models\Costos\Presupuesto;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
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
