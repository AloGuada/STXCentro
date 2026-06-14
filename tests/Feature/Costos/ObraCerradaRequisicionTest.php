<?php

use App\Models\Costos\Requisicion;
use App\Models\Costos\Rubro;
use App\Models\Costos\UsoCfdi;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'costos.requisiciones.crear']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('costos.requisiciones.crear');
    $this->uso = UsoCfdi::factory()->create();
    $this->depto = Departamento::factory()->create();
    Rubro::factory()->create(['ambito' => 'obra']);
});

function crearRequisicion($test, Obra $obra): Requisicion
{
    $obraRubro = $obra->obraRubros()->firstOrFail();

    $test->actingAs($test->user)
        ->post(route('admin.costos.requisiciones.store'), [
            'departamento_id' => $test->depto->id,
            'obra_id' => $obra->id,
            'detalles' => [[
                'descripcion' => 'Material',
                'unidad' => 'pza',
                'cantidad' => 2,
                'obra_rubro_id' => $obraRubro->id,
                'uso_cfdi_id' => $test->uso->id,
            ]],
        ])
        ->assertRedirect();

    return Requisicion::latest('id')->firstOrFail();
}

test('una requisición sobre obra cerrada queda marcada sobre_obra_cerrada', function () {
    $obra = Obra::factory()->create(['estatus' => 'cerrada', 'activa' => false]);

    $requisicion = crearRequisicion($this, $obra);

    expect($requisicion->sobre_obra_cerrada)->toBeTrue();
});

test('una requisición sobre obra abierta no queda marcada', function () {
    $obra = Obra::factory()->create(['estatus' => 'abierta']);

    $requisicion = crearRequisicion($this, $obra);

    expect($requisicion->sobre_obra_cerrada)->toBeFalse();
});
