<?php

use App\Models\Cob\IcsoeSeguimiento;
use App\Models\User;
use Spatie\Permission\Models\Role;

function usuarioCobranza(): User
{
    $user = User::factory()->create();
    Role::firstOrCreate(['name' => 'admin-cobranza', 'guard_name' => 'web']);
    $user->assignRole('admin-cobranza');

    return $user;
}

/** @return array<string, array{count: int, filterHref: string|null}> */
function badgesDe(User $user): array
{
    $response = test()->actingAs($user)->get('/dashboard');
    $response->assertOk();

    return $response->viewData('page')['props']['auth']['badges'] ?? [];
}

it('cuenta los seguimientos pendientes de verificación', function () {
    IcsoeSeguimiento::factory()->pendiente()->count(2)->create();
    IcsoeSeguimiento::factory()->create();

    $badges = badgesDe(usuarioCobranza());

    expect($badges['/admin/cob/icsoe']['count'])->toBe(2);
    expect($badges['/admin/cob/icsoe']['filterHref'])->toBe('/admin/cob/icsoe?estatus=pendiente_verificacion');
});

it('no publica el badge cuando no hay pendientes', function () {
    IcsoeSeguimiento::factory()->count(3)->create();

    expect(badgesDe(usuarioCobranza()))->not->toHaveKey('/admin/cob/icsoe');
});

it('el sidebar no revienta con la tabla vacía', function () {
    // Regresión: badge_configs referencia tabla y columna por dato, así que una
    // fila mal apuntada tira 500 en toda página que dibuje el sidebar.
    test()->actingAs(usuarioCobranza())->get('/dashboard')->assertOk();
});
