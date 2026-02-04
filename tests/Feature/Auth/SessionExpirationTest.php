<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('usuario autenticado puede acceder a rutas protegidas', function () {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk();
});

test('usuario no autenticado es redirigido al login', function () {
    $this->get('/dashboard')
        ->assertRedirect('/login');
});

test('sesion se mantiene activa con actividad reciente', function () {
    $this->actingAs($this->user);

    // Primera peticion
    $this->get('/dashboard')->assertOk();

    // Segunda peticion inmediata
    $this->get('/dashboard')->assertOk();
});

test('logout invalida la sesion correctamente', function () {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk();

    // Hacer logout
    $this->post('/logout');

    // Intentar acceder de nuevo
    $this->get('/dashboard')
        ->assertRedirect('/login');
});

test('middleware auth protege rutas correctamente', function () {
    // Sin autenticacion - redirige a login
    $this->get('/dashboard')
        ->assertRedirect('/login');

    $this->get('/admin/usuarios')
        ->assertRedirect('/login');

    // Con autenticacion - acceso permitido
    $this->actingAs($this->user);

    $this->get('/dashboard')
        ->assertOk();
});

test('configuracion de sesion en produccion tiene valores esperados', function () {
    // En testing usa 'array', pero verificamos la config base
    expect(config('session.lifetime'))->toBeGreaterThan(0);
    expect(config('session.table'))->toBe('sessions');

    // Verificar que en .env de produccion usaria 'database'
    // El driver 'array' es esperado en testing
    expect(config('session.driver'))->toBeIn(['array', 'database']);
});
