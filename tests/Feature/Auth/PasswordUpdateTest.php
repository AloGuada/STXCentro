<?php

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

it('actualiza la contraseña con datos válidos', function () {
    $user = Usuario::factory()->create([
        'password' => Hash::make('secret-current-123'),
    ]);

    $this->actingAs($user)
        ->from('/dashboard')
        ->put('/user/password', [
            'current_password' => 'secret-current-123',
            'password' => 'nueva-clave-456',
            'password_confirmation' => 'nueva-clave-456',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Hash::check('nueva-clave-456', $user->fresh()->password))->toBeTrue();
});

it('rechaza cuando la contraseña actual es incorrecta', function () {
    $user = Usuario::factory()->create([
        'password' => Hash::make('secret-current-123'),
    ]);

    $this->actingAs($user)
        ->from('/dashboard')
        ->put('/user/password', [
            'current_password' => 'password-equivocada',
            'password' => 'nueva-clave-456',
            'password_confirmation' => 'nueva-clave-456',
        ])
        ->assertSessionHasErrors('current_password', errorBag: 'updatePassword');

    expect(Hash::check('secret-current-123', $user->fresh()->password))->toBeTrue();
});
