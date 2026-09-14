<?php

use App\Models\Costos\AfectacionPresupuestal;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Rubro;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\SolicitudPago;
use App\Models\Obra;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'costos.obra-rubros.editar', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('costos.obra-rubros.editar');

    $this->presupuesto = Presupuesto::factory()->paraObra(Obra::factory()->create())->create();
    $this->centro = $this->presupuesto->crearRubro(Rubro::factory()->create(['codigo' => 'MAT01'])->id, 100000);
});

/**
 * Un cargo del documento sobre el centro de costo, fechado.
 */
function cargoDe(Model $entrada, ObraRubro $centro, float $monto, string $fecha, string $estatus = 'aplicado'): RubroAfectado
{
    return RubroAfectado::create([
        'entrada_type' => $entrada::class,
        'entrada_id' => $entrada->getKey(),
        'obra_rubro_id' => $centro->id,
        'monto' => $monto,
        'moneda' => 'mxn',
        'descripcion' => "Cargo de {$monto}",
        'tipo_movimiento' => 'cargo',
        'estatus' => $estatus,
        'fecha_aplicacion' => $fecha,
    ]);
}

function historialDeCargos(object $test): \Illuminate\Testing\TestResponse
{
    return $test->actingAs($test->user)->get("/admin/costos/presupuestos/{$test->presupuesto->id}/edit");
}

test('el historial trae los cargos de la oc con sus solicitudes de pago aprobadas, del más reciente al más antiguo', function () {
    $oc = OrdenCompra::factory()->create();
    $aprobada = SolicitudPago::factory()->aprobada()->create(['orden_compra_id' => $oc->id]);
    SolicitudPago::factory()->pendienteFirma()->create(['orden_compra_id' => $oc->id]);
    $sinOc = SolicitudPago::factory()->aprobada()->create();

    cargoDe($oc, $this->centro, 5000, '2026-08-01 10:00:00');
    cargoDe($sinOc, $this->centro, 1200, '2026-09-01 10:00:00');

    historialDeCargos($this)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/costos/presupuestos/edit')
            ->missing('cargos')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('cargos', 2)
                ->where('cargos.0.monto', 1200)
                ->where('cargos.0.orden_compra', null)
                ->where('cargos.0.solicitudes_pago.0.folio', $sinOc->folio)
                ->where('cargos.1.monto', 5000)
                ->where('cargos.1.centro.codigo', 'MAT01')
                ->where('cargos.1.orden_compra.folio', $oc->folio)
                ->has('cargos.1.solicitudes_pago', 1)
                ->where('cargos.1.solicitudes_pago.0.folio', $aprobada->folio)
                ->where('cargos.1.afectacion', 'ejercido')
                ->where('cargos.1.revertido', false)
            )
        );
});

test('el historial deja fuera lo cancelado, lo vencido y los cargos de otro presupuesto', function () {
    $oc = OrdenCompra::factory()->create();
    $otro = Presupuesto::factory()->paraObra(Obra::factory()->create())->create();
    $centroAjeno = $otro->crearRubro(Rubro::factory()->create()->id, 1000);

    cargoDe($oc, $this->centro, 300, '2026-09-01', 'apartado');
    cargoDe($oc, $this->centro, 400, '2026-09-02', 'cancelado');
    cargoDe($oc, $this->centro, 500, '2026-09-03', 'vencido');
    cargoDe($oc, $centroAjeno, 600, '2026-09-04');

    historialDeCargos($this)->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($reload) => $reload
            ->has('cargos', 1)
            ->where('cargos.0.monto', 300)
            ->where('cargos.0.afectacion', 'apartado')
        )
    );
});

test('el cargo de una oc o afectación cancelada queda marcado como revertido', function () {
    cargoDe(OrdenCompra::factory()->cancelada()->create(), $this->centro, 700, '2026-09-01');
    $afectacion = AfectacionPresupuestal::factory()->create();
    cargoDe($afectacion, $this->centro, 800, '2026-09-02');

    historialDeCargos($this)->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($reload) => $reload
            ->has('cargos', 2)
            ->where('cargos.0.documento.tipo', 'afectacion')
            ->where('cargos.0.documento.folio', $afectacion->folio)
            ->where('cargos.0.revertido', false)
            ->where('cargos.1.revertido', true)
        )
    );
});
