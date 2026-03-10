<?php

use App\Models\Drive\Externo;
use App\Models\Usuario;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $permission = Permission::firstOrCreate(['name' => 'drive.gestionar', 'guard_name' => 'web']);

    $this->admin = Usuario::factory()->create();
    $this->admin->givePermissionTo($permission);
});

test('admin puede ver lista de externos', function () {
    Externo::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get('/admin/drive/externos')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/drive/externos/index'));
});

test('admin puede crear externo', function () {
    $this->actingAs($this->admin)
        ->post('/admin/drive/externos', [
            'nombre' => 'Juan Test',
            'email' => 'juan@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'telefono' => '1234567890',
            'empresa' => 'Test Corp',
        ])
        ->assertRedirect('/admin/drive/externos');

    $this->assertDatabaseHas('drive_externos', [
        'nombre' => 'Juan Test',
        'email' => 'juan@test.com',
    ]);
});

test('admin puede actualizar externo', function () {
    $externo = Externo::factory()->create();

    $this->actingAs($this->admin)
        ->put("/admin/drive/externos/{$externo->id}", [
            'nombre' => 'Nombre Actualizado',
            'email' => $externo->email,
        ])
        ->assertRedirect('/admin/drive/externos');

    $this->assertDatabaseHas('drive_externos', [
        'id' => $externo->id,
        'nombre' => 'Nombre Actualizado',
    ]);
});

test('admin puede eliminar externo', function () {
    $externo = Externo::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/admin/drive/externos/{$externo->id}")
        ->assertRedirect('/admin/drive/externos');

    $this->assertDatabaseMissing('drive_externos', ['id' => $externo->id]);
});

test('usuario sin permiso no puede acceder a externos', function () {
    $user = Usuario::factory()->create();

    $this->actingAs($user)
        ->get('/admin/drive/externos')
        ->assertForbidden();
});
