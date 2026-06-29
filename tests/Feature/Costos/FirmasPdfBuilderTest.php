<?php

use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\Permiso;
use App\Models\Departamento;
use App\Models\User;
use App\Services\Costos\FirmasPdfBuilder;

beforeEach(function () {
    $this->builder = new FirmasPdfBuilder;
    $this->deptoA = Departamento::factory()->create();
    $this->deptoB = Departamento::factory()->create();

    // Permisos de dos tipos de documento que comparten el nivel 1.
    $this->sol1 = Permiso::factory()->create(['descripcion' => 'Jefe', 'nivel' => 1, 'tipo_aprobacion' => 'solicitud_pago']);
    $this->sol2 = Permiso::factory()->create(['descripcion' => 'Gerente', 'nivel' => 2, 'tipo_aprobacion' => 'solicitud_pago']);
    $this->req1 = Permiso::factory()->create(['descripcion' => 'Compras', 'nivel' => 1, 'tipo_aprobacion' => 'requisicion']);

    // El departamento A participa en ambos tipos; B solo en el nivel 1 de solicitud.
    AprobacionDepartamento::factory()->create(['departamento_id' => $this->deptoA->id, 'permiso_id' => $this->sol1->id]);
    AprobacionDepartamento::factory()->create(['departamento_id' => $this->deptoA->id, 'permiso_id' => $this->sol2->id]);
    AprobacionDepartamento::factory()->create(['departamento_id' => $this->deptoA->id, 'permiso_id' => $this->req1->id]);
    AprobacionDepartamento::factory()->create(['departamento_id' => $this->deptoB->id, 'permiso_id' => $this->sol1->id]);
});

function aprobacion(int $nivel, string $estatus = 'pendiente', $aprobador = null): object
{
    return (object) ['nivel' => $nivel, 'estatus' => $estatus, 'aprobador' => $aprobador, 'fecha_respuesta' => null];
}

test('incluye solo los niveles del tipo de documento (no mezcla otros tipos)', function () {
    $aprobaciones = collect([
        aprobacion(1, 'aprobada', User::factory()->create()),
        aprobacion(2),
    ]);

    $firmas = $this->builder->build('solicitud_pago', $this->deptoA->id, $aprobaciones);

    expect($firmas)->toHaveCount(2);
    // El nivel 1 debe traer el permiso de solicitud ('Jefe'), no el de requisición ('Compras').
    expect($firmas[0]->permiso->descripcion)->toBe('Jefe')
        ->and($firmas[0]->aprobada)->toBeTrue()
        ->and($firmas[1]->permiso->descripcion)->toBe('Gerente')
        ->and($firmas[1]->aprobada)->toBeFalse();
});

test('filtra por departamento: B no ve niveles que no tiene asignados', function () {
    // Aunque haya aprobación de nivel 2, el depto B no tiene ese permiso asignado.
    $aprobaciones = collect([aprobacion(1, 'aprobada'), aprobacion(2)]);

    $firmas = $this->builder->build('solicitud_pago', $this->deptoB->id, $aprobaciones);

    expect($firmas)->toHaveCount(1)
        ->and($firmas[0]->permiso->nivel)->toBe(1);
});

test('para requisición solo trae el permiso de ese tipo', function () {
    $aprobaciones = collect([aprobacion(1, 'aprobada')]);

    $firmas = $this->builder->build('requisicion', $this->deptoA->id, $aprobaciones);

    expect($firmas)->toHaveCount(1)
        ->and($firmas[0]->permiso->descripcion)->toBe('Compras');
});

test('solo incluye niveles que tienen una aprobación en la cadena', function () {
    // Sin aprobación de nivel 2: no debe pintar esa columna aunque el permiso exista.
    $aprobaciones = collect([aprobacion(1, 'aprobada')]);

    $firmas = $this->builder->build('solicitud_pago', $this->deptoA->id, $aprobaciones);

    expect($firmas)->toHaveCount(1)
        ->and($firmas[0]->permiso->nivel)->toBe(1);
});
