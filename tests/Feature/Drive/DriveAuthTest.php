<?php

use App\Models\Drive\Externo;

beforeEach(function () {
    $this->externo = Externo::factory()->create([
        'email' => 'externo@test.com',
        'password' => bcrypt('password'),
        'activo' => true,
    ]);
});

test('muestra la pagina de login del drive', function () {
    $this->get('/drive/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('drive/auth/login'));
});

test('externo puede iniciar sesion con credenciales correctas', function () {
    $this->post('/drive/login', [
        'email' => 'externo@test.com',
        'password' => 'password',
    ])->assertRedirect('/drive');

    $this->assertAuthenticatedAs($this->externo, 'externo');
});

test('externo no puede iniciar sesion con credenciales incorrectas', function () {
    $this->post('/drive/login', [
        'email' => 'externo@test.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('externo');
});

test('externo inactivo no puede iniciar sesion', function () {
    $this->externo->update(['activo' => false]);

    $this->post('/drive/login', [
        'email' => 'externo@test.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('externo');
});

test('externo puede cerrar sesion', function () {
    $this->actingAs($this->externo, 'externo')
        ->post('/drive/logout')
        ->assertRedirect('/drive/login');

    $this->assertGuest('externo');
});

test('rutas del drive requieren autenticacion de externo', function () {
    $this->get('/drive')
        ->assertRedirect('/drive/login');
});

test('usuario web no puede acceder al drive', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get('/drive')
        ->assertRedirect('/drive/login');
});

test('externo autenticado es redirigido del login al dashboard', function () {
    $this->actingAs($this->externo, 'externo')
        ->get('/drive/login')
        ->assertRedirect('/drive');
});
