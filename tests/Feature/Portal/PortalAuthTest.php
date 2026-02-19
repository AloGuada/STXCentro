<?php

use App\Models\Proveedor;

beforeEach(function () {
    $this->proveedor = Proveedor::factory()->create([
        'email' => 'proveedor@test.com',
        'password' => bcrypt('password'),
        'tiene_acceso_portal' => true,
        'activo' => true,
    ]);
});

test('muestra la pagina de login del portal', function () {
    $this->get('/portal/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('portal/auth/login'));
});

test('proveedor puede iniciar sesion con credenciales correctas', function () {
    $this->post('/portal/login', [
        'email' => 'proveedor@test.com',
        'password' => 'password',
    ])->assertRedirect('/portal');

    $this->assertAuthenticatedAs($this->proveedor, 'proveedor');
});

test('proveedor no puede iniciar sesion con credenciales incorrectas', function () {
    $this->post('/portal/login', [
        'email' => 'proveedor@test.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('proveedor');
});

test('proveedor sin acceso portal no puede iniciar sesion', function () {
    $this->proveedor->update(['tiene_acceso_portal' => false]);

    $this->post('/portal/login', [
        'email' => 'proveedor@test.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('proveedor');
});

test('proveedor inactivo no puede iniciar sesion', function () {
    $this->proveedor->update(['activo' => false]);

    $this->post('/portal/login', [
        'email' => 'proveedor@test.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('proveedor');
});

test('proveedor puede cerrar sesion', function () {
    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/logout')
        ->assertRedirect('/portal/login');

    $this->assertGuest('proveedor');
});

test('rutas del portal requieren autenticacion de proveedor', function () {
    $this->get('/portal')
        ->assertRedirect('/portal/login');
});

test('usuario web no puede acceder al portal', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get('/portal')
        ->assertRedirect('/portal/login');
});

test('proveedor autenticado es redirigido del login al dashboard', function () {
    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal/login')
        ->assertRedirect('/portal');
});
