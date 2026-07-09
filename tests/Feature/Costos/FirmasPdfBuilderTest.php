<?php

use App\Models\Costos\Permiso;
use App\Models\Departamento;
use App\Models\User;
use App\Services\Costos\FirmasPdfBuilder;

beforeEach(function () {
    $this->builder = new FirmasPdfBuilder;
    $this->depto = Departamento::factory()->create();

    // Config vigente de niveles (solo para rotular la columna).
    Permiso::factory()->create(['descripcion' => 'Jefe', 'nivel' => 1, 'tipo_aprobacion' => 'solicitud_pago']);
    Permiso::factory()->create(['descripcion' => 'Gerente', 'nivel' => 2, 'tipo_aprobacion' => 'solicitud_pago']);
    Permiso::factory()->create(['descripcion' => 'Compras', 'nivel' => 1, 'tipo_aprobacion' => 'requisicion']);
});

function aprobacion(int $nivel, string $estatus = 'pendiente', $aprobador = null): object
{
    return (object) ['nivel' => $nivel, 'estatus' => $estatus, 'aprobador' => $aprobador, 'fecha_respuesta' => null];
}

test('crea una columna por nivel de la cadena, ordenadas por nivel', function () {
    $aprobaciones = collect([
        aprobacion(2),
        aprobacion(1, 'aprobada', User::factory()->create()),
    ]);

    $firmas = $this->builder->build('solicitud_pago', $this->depto->id, $aprobaciones);

    expect($firmas)->toHaveCount(2)
        ->and($firmas[0]->permiso->nivel)->toBe(1)
        ->and($firmas[1]->permiso->nivel)->toBe(2)
        ->and($firmas[0]->aprobada)->toBeTrue()
        ->and($firmas[1]->aprobada)->toBeFalse();
});

test('rotula cada nivel con la descripción del permiso de ese tipo', function () {
    $aprobaciones = collect([aprobacion(1, 'aprobada'), aprobacion(2)]);

    $firmas = $this->builder->build('solicitud_pago', $this->depto->id, $aprobaciones);

    expect($firmas[0]->permiso->descripcion)->toBe('Jefe')
        ->and($firmas[1]->permiso->descripcion)->toBe('Gerente');
});

test('para requisición rotula con el permiso de ese tipo', function () {
    $aprobaciones = collect([aprobacion(1, 'aprobada')]);

    $firmas = $this->builder->build('requisicion', $this->depto->id, $aprobaciones);

    expect($firmas)->toHaveCount(1)
        ->and($firmas[0]->permiso->descripcion)->toBe('Compras');
});

test('conserva todas las firmas de la cadena aunque los niveles configurados hayan cambiado', function () {
    // La solicitud se firmó con 3 niveles; después alguien reconfiguró los
    // niveles y ya no existe un permiso para el nivel 3. La firma debe seguir.
    $aprobaciones = collect([
        aprobacion(1, 'aprobada', User::factory()->create()),
        aprobacion(2, 'aprobada', User::factory()->create()),
        aprobacion(3, 'aprobada', User::factory()->create()),
    ]);

    $firmas = $this->builder->build('solicitud_pago', $this->depto->id, $aprobaciones);

    expect($firmas)->toHaveCount(3)
        ->and($firmas->pluck('permiso.nivel')->all())->toBe([1, 2, 3])
        // El nivel 3 no tiene permiso en la config actual: se rotula vacío sin romper.
        ->and($firmas[2]->permiso->descripcion)->toBe('')
        ->and($firmas[2]->aprobada)->toBeTrue();
});

test('candidatos lista los aprobadores asignados al nivel (multiusuario)', function () {
    $ana = User::factory()->create(['name' => 'Ana']);
    $beto = User::factory()->create(['name' => 'Beto']);

    // Un nivel con dos candidatos pendientes.
    $aprobaciones = collect([
        aprobacion(1, 'pendiente', $ana),
        aprobacion(1, 'pendiente', $beto),
    ]);

    $firmas = $this->builder->build('solicitud_pago', $this->depto->id, $aprobaciones);

    expect($firmas)->toHaveCount(1)
        ->and($firmas[0]->candidatos->all())->toBe(['Ana', 'Beto'])
        ->and($firmas[0]->aprobada)->toBeFalse();
});

test('toma al aprobador que firmó el nivel', function () {
    $ana = User::factory()->create(['name' => 'Ana']);
    $beto = User::factory()->create(['name' => 'Beto']);

    // Nivel con dos candidatos; Beto firmó.
    $aprobaciones = collect([
        aprobacion(1, 'pendiente', $ana),
        aprobacion(1, 'aprobada', $beto),
    ]);

    $firmas = $this->builder->build('solicitud_pago', $this->depto->id, $aprobaciones);

    expect($firmas)->toHaveCount(1)
        ->and($firmas[0]->aprobada)->toBeTrue()
        ->and($firmas[0]->aprobador->name)->toBe('Beto');
});
