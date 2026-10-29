<?php

use App\Models\Obra;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Sublote;
use App\Models\User;
use Database\Seeders\QalRegistro2DevSeeder;
use Spatie\Permission\Models\Permission;

/**
 * La obra de prueba de Registro 2: se puede correr dos veces y deja cada caso
 * como lo promete su docblock.
 */
test('siembra la obra de prueba de registro 2 y se puede repetir', function () {
    $this->seed(QalRegistro2DevSeeder::class);
    $this->seed(QalRegistro2DevSeeder::class);

    $obraId = Obra::query()->where('no', 'PRUEBA-REGISTRO2')->value('id');
    Permission::firstOrCreate(['name' => 'qal.registros.ver', 'guard_name' => 'web']);
    $usuario = User::factory()->create();
    $usuario->givePermissionTo('qal.registros.ver');

    expect(Inspeccion::query()->where('obra_id', $obraId)->count())->toBe(23)
        ->and(Sublote::query()->whereHas('lote', fn ($lote) => $lote->where('obra_id', $obraId))->count())->toBe(6);

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['obra' => $obraId]))
        ->assertInertia(fn ($page) => $page
            ->where('conteo.registros', 23)
            ->where('conteo.piezas', 15)
            ->has('inspectores', 4)
            ->where('inspectores.3', fn ($inspector) => $inspector['nombre'] === 'Diego Ruiz' && $inspector['activo'] === false));

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['obra' => $obraId, 'periodo' => '7d']))
        ->assertInertia(fn ($page) => $page->where('conteo.registros', 11));

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['obra' => $obraId, 'que' => 'acc']))
        ->assertInertia(fn ($page) => $page
            ->where('conteo.registros', 5)
            ->where('sublotes.data', fn ($sublotes) => collect($sublotes)->contains('sin_disposicion', true)));
});
