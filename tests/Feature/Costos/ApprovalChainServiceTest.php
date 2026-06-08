<?php

use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\Permiso;
use App\Models\Costos\Requisicion;
use App\Models\Costos\SolicitudPago;
use App\Models\Departamento;
use App\Models\User;
use App\Services\Costos\ApprovalChainService;

beforeEach(function () {
    $this->service = app(ApprovalChainService::class);
    $this->depto = Departamento::factory()->create();
});

function configurarNivel(Departamento $depto, string $tipo, int $nivel): void
{
    $permiso = Permiso::create([
        'descripcion' => "Nivel {$nivel} {$tipo}",
        'nivel' => $nivel,
        'tipo_aprobacion' => $tipo,
    ]);
    AprobacionDepartamento::create([
        'departamento_id' => $depto->id,
        'permiso_id' => $permiso->id,
        'aprobador_id' => User::factory()->create()->id,
    ]);
}

test('crea una aprobacion por nivel del tipo correcto y devuelve el conteo', function () {
    configurarNivel($this->depto, 'requisicion', 1);
    configurarNivel($this->depto, 'requisicion', 2);
    configurarNivel($this->depto, 'solicitud_pago', 1); // no debe entrar

    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id]);

    $creadas = $this->service->crearCadenaAprobaciones($req);

    expect($creadas)->toBe(2);
    expect($req->aprobaciones()->count())->toBe(2);
    expect($req->aprobaciones()->pluck('nivel')->map(fn ($n) => (int) $n)->sort()->values()->all())->toBe([1, 2]);
});

test('filtra por el tipoAprobacion del aprobable', function () {
    configurarNivel($this->depto, 'requisicion', 1);
    configurarNivel($this->depto, 'solicitud_pago', 1);

    $sp = SolicitudPago::factory()->create(['departamento_id' => $this->depto->id]);

    expect($this->service->crearCadenaAprobaciones($sp))->toBe(1);
    expect($sp->aprobaciones()->count())->toBe(1);
});

test('devuelve 0 cuando el departamento no tiene cadena configurada', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id]);

    expect($this->service->crearCadenaAprobaciones($req))->toBe(0);
    expect($req->aprobaciones()->count())->toBe(0);
});
