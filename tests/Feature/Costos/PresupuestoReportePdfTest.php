<?php

use App\Models\Costos\ObraRubro;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'costos.obra-rubros.ver', 'guard_name' => 'web']);
});

test('el reporte de presupuestos descarga un PDF', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('costos.obra-rubros.ver');

    ObraRubro::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('admin.costos.presupuestos.reporte-pdf'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('el reporte de presupuestos sube el limite de memoria de la peticion', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('costos.obra-rubros.ver');

    ini_set('memory_limit', '256M');

    $this->actingAs($user)
        ->get(route('admin.costos.presupuestos.reporte-pdf'))
        ->assertOk();

    expect(ini_get('memory_limit'))->toBe('1024M');
});
