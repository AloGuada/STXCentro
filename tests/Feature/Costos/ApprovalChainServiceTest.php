<?php

use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Permiso;
use App\Models\Costos\Requisicion;
use App\Models\Costos\SolicitudPago;
use App\Models\Departamento;
use App\Models\User;
use App\Services\Costos\ApartadoPresupuestal;
use App\Services\Costos\ApprovalChainService;

beforeEach(function () {
    $this->service = app(ApprovalChainService::class);
    $this->depto = Departamento::factory()->create();
});

function configurarNivel(Departamento $depto, string $tipo, int $nivel, bool $omitir = false): void
{
    $permiso = Permiso::create([
        'descripcion' => "Nivel {$nivel} {$tipo}",
        'nivel' => $nivel,
        'tipo_aprobacion' => $tipo,
        'omitir_si_presupuesto_reservado' => $omitir,
    ]);
    AprobacionDepartamento::create([
        'departamento_id' => $depto->id,
        'permiso_id' => $permiso->id,
        'aprobador_id' => User::factory()->create()->id,
    ]);
}

function apartarPresupuesto(Requisicion $req): void
{
    $rubro = ObraRubro::factory()->create();
    app(ApartadoPresupuestal::class)->apartarDocumento(
        $req,
        [['obra_rubro_id' => $rubro->id, 'monto' => 100, 'descripcion' => 'test']],
        User::factory()->create()->id,
    );
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

test('tienePresupuestoReservado es true con apartado vigente y false sin apartado', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id]);
    expect($req->tienePresupuestoReservado())->toBeFalse();

    apartarPresupuesto($req);
    expect($req->fresh()->tienePresupuestoReservado())->toBeTrue();
});

test('salta el nivel marcado cuando hay presupuesto reservado', function () {
    configurarNivel($this->depto, 'requisicion', 1, omitir: true);
    configurarNivel($this->depto, 'requisicion', 2, omitir: false);

    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id]);
    apartarPresupuesto($req);

    $creadas = $this->service->crearCadenaAprobaciones($req);

    // Solo el nivel 2 (no marcado) genera firma; el 1 se salta.
    expect($creadas)->toBe(1);
    expect($req->aprobaciones()->pluck('nivel')->map(fn ($n) => (int) $n)->all())->toBe([2]);
});

test('NO salta el nivel marcado si no hay presupuesto reservado', function () {
    configurarNivel($this->depto, 'requisicion', 1, omitir: true);
    configurarNivel($this->depto, 'requisicion', 2, omitir: false);

    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id]);
    // sin apartado → no reservado

    expect($this->service->crearCadenaAprobaciones($req))->toBe(2);
});

test('si todos los niveles se saltan, no crea ninguna aprobacion (auto aprobable)', function () {
    configurarNivel($this->depto, 'requisicion', 1, omitir: true);
    configurarNivel($this->depto, 'requisicion', 2, omitir: true);

    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id]);
    apartarPresupuesto($req);

    expect($this->service->crearCadenaAprobaciones($req))->toBe(0);
    expect($this->service->tieneCadenaConfigurada($req))->toBeTrue();
});
