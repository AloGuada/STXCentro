<?php

use App\Models\Usuario;

test('un usuario dado de baja no puede iniciar sesión', function () {
    $usuario = Usuario::factory()->dadoDeBaja()->create();

    $this->post(route('login.store'), [
        'email' => $usuario->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('un usuario activo sí puede iniciar sesión', function () {
    $usuario = Usuario::factory()->create();

    $this->post(route('login.store'), [
        'email' => $usuario->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

test('un administrador puede dar de baja a un usuario', function () {
    $admin = Usuario::factory()->create();
    $usuario = Usuario::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.usuarios.estado', $usuario))
        ->assertRedirect();

    $usuario->refresh();
    expect($usuario->activo)->toBeFalse();
    expect($usuario->fecha_baja)->not->toBeNull();
});

test('dar de baja de nuevo reactiva al usuario', function () {
    $admin = Usuario::factory()->create();
    $usuario = Usuario::factory()->dadoDeBaja()->create();

    $this->actingAs($admin)
        ->patch(route('admin.usuarios.estado', $usuario));

    $usuario->refresh();
    expect($usuario->activo)->toBeTrue();
    expect($usuario->fecha_baja)->toBeNull();
});

test('un usuario no puede cambiar el estado de su propia cuenta', function () {
    $admin = Usuario::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.usuarios.estado', $admin))
        ->assertSessionHasErrors('estado');

    $admin->refresh();
    expect($admin->activo)->toBeTrue();
});

test('un usuario con sesión activa que es dado de baja es expulsado', function () {
    $usuario = Usuario::factory()->create();

    $this->actingAs($usuario)->get('/dashboard')->assertOk();

    $usuario->darDeBaja();

    $this->actingAs($usuario)
        ->get('/dashboard')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
