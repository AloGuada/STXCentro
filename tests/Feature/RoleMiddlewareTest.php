<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('el alias de middleware role esta registrado y permite acceso al rol correcto', function () {
    $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/admin/media')
        ->assertOk();
});

test('el middleware role rechaza con 403 a quien no tiene el rol', function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/media')
        ->assertForbidden();
});
