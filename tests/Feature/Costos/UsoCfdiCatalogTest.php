<?php

use App\Models\Costos\UsoCfdi;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'costos.usos-cfdi.ver',
        'costos.usos-cfdi.crear',
        'costos.usos-cfdi.editar',
        'costos.usos-cfdi.eliminar',
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo([
        'costos.usos-cfdi.ver',
        'costos.usos-cfdi.crear',
        'costos.usos-cfdi.editar',
        'costos.usos-cfdi.eliminar',
    ]);
});

test('index lista los usos de CFDI', function () {
    UsoCfdi::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get('/admin/costos/usos-cfdi')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/costos/usos-cfdi/index')->has('usosCfdi.data', 3));
});

test('crea un uso de CFDI', function () {
    $this->actingAs($this->admin)
        ->post('/admin/costos/usos-cfdi', ['clave' => 'G01', 'descripcion' => 'Adquisición de mercancías', 'activo' => true])
        ->assertRedirect();

    $this->assertDatabaseHas('costos_usos_cfdi', ['clave' => 'G01']);
});

test('la clave debe ser única', function () {
    UsoCfdi::factory()->create(['clave' => 'G03']);

    $this->actingAs($this->admin)
        ->post('/admin/costos/usos-cfdi', ['clave' => 'G03', 'descripcion' => 'Gastos en general'])
        ->assertSessionHasErrors(['clave']);
});

test('actualiza un uso de CFDI', function () {
    $uso = UsoCfdi::factory()->create(['descripcion' => 'Viejo']);

    $this->actingAs($this->admin)
        ->put("/admin/costos/usos-cfdi/{$uso->id}", ['clave' => $uso->clave, 'descripcion' => 'Nuevo', 'activo' => false])
        ->assertRedirect();

    expect($uso->fresh()->descripcion)->toBe('Nuevo');
    expect($uso->fresh()->activo)->toBeFalse();
});

test('no se puede eliminar un uso con partidas asociadas', function () {
    $uso = UsoCfdi::factory()->create();
    \App\Models\Costos\RequisicionDetalle::factory()->create(['uso_cfdi_id' => $uso->id]);

    $this->actingAs($this->admin)
        ->delete("/admin/costos/usos-cfdi/{$uso->id}")
        ->assertSessionHasErrors(['delete']);

    $this->assertDatabaseHas('costos_usos_cfdi', ['id' => $uso->id]);
});

test('requiere permiso para gestionar el catálogo', function () {
    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso)
        ->get('/admin/costos/usos-cfdi')
        ->assertForbidden();
});
