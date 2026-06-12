<?php

use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraVersion;
use App\Models\Cotiz\Tarjeta;
use App\Models\User;
use App\Services\Cotiz\VersionManager;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-cotiz');
    $this->actingAs($this->user);
});

test('index lista las versiones de la obra', function () {
    $obra = Obra::factory()->create();
    ObraVersion::factory()->count(2)->create(['obra_id' => $obra->id]);

    $this->get(route('admin.cotiz.versiones.index', $obra))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/cotiz/versiones/index')
            ->has('versiones', 2)
            ->where('diff', null)
        );
});

test('store crea un snapshot con el autor', function () {
    $obra = Obra::factory()->create();
    Tarjeta::factory()->create(['obra_id' => $obra->id]);

    $this->post(route('admin.cotiz.versiones.store', $obra), ['nombre' => 'Revisión 1'])
        ->assertRedirect();

    $version = ObraVersion::where('obra_id', $obra->id)->firstOrFail();
    expect($version->nombre)->toBe('Revisión 1')
        ->and($version->creado_por)->toBe($this->user->id)
        ->and($version->auto)->toBeFalse()
        ->and($version->snapshot)->toHaveKey('tarjetas');
});

test('restaurar deja un respaldo automático (lineal)', function () {
    $obra = Obra::factory()->create();
    Tarjeta::factory()->create(['obra_id' => $obra->id]);
    $v1 = app(VersionManager::class)->crear($obra, 'V1');

    $this->post(route('admin.cotiz.versiones.restaurar', [$obra, $v1]))->assertRedirect();

    expect(ObraVersion::where('obra_id', $obra->id)->count())->toBe(2)
        ->and(ObraVersion::where('obra_id', $obra->id)->where('auto', true)->count())->toBe(1);
});

test('index con ?a=&b= devuelve el diff', function () {
    $obra = Obra::factory()->create();
    $vm = app(VersionManager::class);
    $a = $vm->crear($obra, 'A');
    Tarjeta::factory()->create(['obra_id' => $obra->id]);
    $b = $vm->crear($obra, 'B');

    $this->get(route('admin.cotiz.versiones.index', [$obra, 'a' => $a->id, 'b' => $b->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('diff.grupos.tarjetas')
            ->where('diff.grupos.tarjetas.de', 0)
            ->where('diff.grupos.tarjetas.a', 1)
            ->where('comparando.a', $a->id)
        );
});

test('no se puede restaurar una versión de otra obra', function () {
    $obra = Obra::factory()->create();
    $otra = Obra::factory()->create();
    $version = ObraVersion::factory()->create(['obra_id' => $otra->id]);

    $this->post(route('admin.cotiz.versiones.restaurar', [$obra, $version]))->assertNotFound();
});
