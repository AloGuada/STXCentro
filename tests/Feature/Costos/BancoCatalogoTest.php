<?php

use App\Models\Banco;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'costos.bancos.ver',
        'costos.bancos.crear',
        'costos.bancos.editar',
        'costos.bancos.eliminar',
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'costos.bancos.ver',
        'costos.bancos.crear',
        'costos.bancos.editar',
        'costos.bancos.eliminar',
    ]);
});

test('index se renderiza', function () {
    Banco::factory()->count(2)->create();

    $this->actingAs($this->user)
        ->get(route('admin.bancos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/bancos/index')->has('bancos', 2));
});

test('crea un banco', function () {
    $this->actingAs($this->user)
        ->post(route('admin.bancos.store'), ['nombre' => 'BBVA', 'es_pagador' => false, 'activo' => true])
        ->assertRedirect(route('admin.bancos.index'));

    $this->assertDatabaseHas('bancos', ['nombre' => 'BBVA', 'es_pagador' => false]);
});

test('el banco pagador exige dígitos de cuenta', function () {
    $this->actingAs($this->user)
        ->post(route('admin.bancos.store'), ['nombre' => 'Banorte', 'es_pagador' => true])
        ->assertSessionHasErrors(['digitos_cuenta']);
});

test('marcar un banco como pagador desmarca al anterior', function () {
    $viejo = Banco::factory()->pagador(10)->create(['nombre' => 'Banorte']);
    $nuevo = Banco::factory()->create(['nombre' => 'Santander']);

    $this->actingAs($this->user)
        ->put(route('admin.bancos.update', $nuevo), ['nombre' => 'Santander', 'es_pagador' => true, 'digitos_cuenta' => 11, 'activo' => true])
        ->assertRedirect(route('admin.bancos.index'));

    expect($nuevo->fresh()->es_pagador)->toBeTrue();
    expect($viejo->fresh()->es_pagador)->toBeFalse();
    expect(Banco::pagador()->count())->toBe(1);
});

test('no se permite nombre duplicado', function () {
    Banco::factory()->create(['nombre' => 'HSBC']);

    $this->actingAs($this->user)
        ->post(route('admin.bancos.store'), ['nombre' => 'HSBC'])
        ->assertSessionHasErrors(['nombre']);
});

test('elimina un banco', function () {
    $banco = Banco::factory()->create();

    $this->actingAs($this->user)
        ->delete(route('admin.bancos.destroy', $banco))
        ->assertRedirect(route('admin.bancos.index'));

    $this->assertDatabaseMissing('bancos', ['id' => $banco->id]);
});

test('sin permiso no puede acceder al catálogo', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.bancos.index'))
        ->assertForbidden();
});
