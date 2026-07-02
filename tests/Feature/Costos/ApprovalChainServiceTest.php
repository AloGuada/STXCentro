<?php

use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OmitirRubro;
use App\Models\Costos\Permiso;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\Rubro;
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
    ]);
    AprobacionDepartamento::create([
        'departamento_id' => $depto->id,
        'permiso_id' => $permiso->id,
        'aprobador_id' => User::factory()->create()->id,
        'omitir_si_presupuesto_reservado' => $omitir,
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

/**
 * Configura un nivel saltable (omitir=true) restringido a los rubros dados, y
 * devuelve el Permiso. Con $rubrosPermitidos vacío, aplica a todos los centros.
 *
 * @param  list<int>  $rubrosPermitidos
 */
function nivelSaltableConRubros(Departamento $depto, array $rubrosPermitidos = []): Permiso
{
    $permiso = Permiso::create(['descripcion' => 'N1', 'nivel' => 1, 'tipo_aprobacion' => 'requisicion']);
    AprobacionDepartamento::create([
        'departamento_id' => $depto->id,
        'permiso_id' => $permiso->id,
        'aprobador_id' => User::factory()->create()->id,
        'omitir_si_presupuesto_reservado' => true,
    ]);
    foreach ($rubrosPermitidos as $rubroId) {
        OmitirRubro::create(['departamento_id' => $depto->id, 'permiso_id' => $permiso->id, 'rubro_id' => $rubroId]);
    }

    return $permiso;
}

function reqConRubro(Departamento $depto, int $rubroId): Requisicion
{
    $obraRubro = ObraRubro::factory()->create(['rubro_id' => $rubroId]);
    $req = Requisicion::factory()->create(['departamento_id' => $depto->id]);
    RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'obra_rubro_id' => $obraRubro->id]);
    apartarPresupuesto($req); // deja presupuesto reservado

    return $req->fresh();
}

test('salta el nivel cuando los centros del documento están dentro de la lista permitida', function () {
    $rubro = Rubro::factory()->create();
    nivelSaltableConRubros($this->depto, [$rubro->id]);

    $req = reqConRubro($this->depto, $rubro->id);

    expect($this->service->crearCadenaAprobaciones($req))->toBe(0);
});

test('NO salta el nivel si el documento toca un centro fuera de la lista permitida', function () {
    $permitido = Rubro::factory()->create();
    $fuera = Rubro::factory()->create();
    nivelSaltableConRubros($this->depto, [$permitido->id]);

    $req = reqConRubro($this->depto, $fuera->id);

    // El centro no está permitido → no se salta → crea la firma del nivel.
    expect($this->service->crearCadenaAprobaciones($req))->toBe(1);
});

test('lista de rubros vacía salta para cualquier centro de costo', function () {
    nivelSaltableConRubros($this->depto, []); // sin rubros = todos

    $req = reqConRubro($this->depto, Rubro::factory()->create()->id);

    expect($this->service->crearCadenaAprobaciones($req))->toBe(0);
});

test('si todos los niveles se saltan, no crea ninguna aprobacion (auto aprobable)', function () {
    configurarNivel($this->depto, 'requisicion', 1, omitir: true);
    configurarNivel($this->depto, 'requisicion', 2, omitir: true);

    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id]);
    apartarPresupuesto($req);

    expect($this->service->crearCadenaAprobaciones($req))->toBe(0);
    expect($this->service->tieneCadenaConfigurada($req))->toBeTrue();
});
