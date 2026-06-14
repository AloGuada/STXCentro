<?php

use App\Models\Obra;
use App\Models\User;
use Spatie\Permission\Models\Permission;

test('cerrar una obra requiere permiso y sincroniza estatus/activa', function () {
    Permission::firstOrCreate(['name' => 'cob.obras.cerrar']);
    $user = User::factory()->create();
    $user->givePermissionTo('cob.obras.cerrar');

    $obra = Obra::factory()->create(['estatus' => 'abierta', 'activa' => true]);

    $this->actingAs($user)
        ->put(route('admin.cob.obras.cambiar-estado', $obra), ['estatus' => 'cerrada'])
        ->assertRedirect();

    $obra->refresh();
    expect($obra->estatus)->toBe('cerrada');
    expect($obra->activa)->toBeFalse();

    // Reabrir vuelve a sincronizar.
    $this->actingAs($user)
        ->put(route('admin.cob.obras.cambiar-estado', $obra), ['estatus' => 'abierta'])
        ->assertRedirect();

    $obra->refresh();
    expect($obra->estatus)->toBe('abierta');
    expect($obra->activa)->toBeTrue();
});

test('sin permiso cob.obras.cerrar no se puede cambiar el estado', function () {
    $user = User::factory()->create();
    $obra = Obra::factory()->create(['estatus' => 'abierta']);

    $this->actingAs($user)
        ->put(route('admin.cob.obras.cambiar-estado', $obra), ['estatus' => 'cerrada'])
        ->assertForbidden();

    expect($obra->fresh()->estatus)->toBe('abierta');
});
