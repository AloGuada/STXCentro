<?php

use App\Models\Costos\Presupuesto;
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
    $this->rubro = Rubro::factory()->create(['ambito' => 'obra']);
});

/** Obra con presupuesto (cerrado o activo) y un rubro sembrado. */
function obraConPresupuesto($test, bool $cerrado): Obra
{
    $obra = Obra::factory()->create();
    $presupuesto = Presupuesto::factory()->paraObra($obra)->create();
    if ($cerrado) {
        $presupuesto->update(['estatus' => 'cerrado']);
    }
    $presupuesto->crearRubro($test->rubro->id, 1000);

    return $obra;
}

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

test('una requisición sobre presupuesto cerrado queda marcada sobre_obra_cerrada', function () {
    $obra = obraConPresupuesto($this, cerrado: true);

    $requisicion = crearRequisicion($this, $obra);

    expect($requisicion->sobre_obra_cerrada)->toBeTrue();
});

test('una requisición sobre presupuesto activo no queda marcada', function () {
    $obra = obraConPresupuesto($this, cerrado: false);

    $requisicion = crearRequisicion($this, $obra);

    expect($requisicion->sobre_obra_cerrada)->toBeFalse();
});
