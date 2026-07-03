<?php

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\Factura;
use App\Models\Costos\FacturaDetalle;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RubroAfectado;

/**
 * Arma una requisición con una OC generada y todo su downstream (detalles,
 * factura, entrega) más un rubro afectado que reserva presupuesto.
 *
 * @return array{requisicion: Requisicion, obraRubro: ObraRubro, oc: OrdenCompra, factura: Factura, entrega: Entrega}
 */
function armarRequisicionCompleta(): array
{
    $obraRubro = ObraRubro::factory()->create(['acumulado' => 5000]);

    $requisicion = Requisicion::factory()->aprobada()->create(['folio' => 'REQ-2601-0001']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $requisicion->id]);

    $oc = OrdenCompra::factory()->create(['requisicion_id' => $requisicion->id]);
    OrdenCompraDetalle::factory()->create(['orden_compra_id' => $oc->id]);

    $factura = Factura::factory()->create(['orden_compra_id' => $oc->id]);
    FacturaDetalle::factory()->create(['factura_id' => $factura->id]);

    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::factory()->create(['entrega_id' => $entrega->id]);

    RubroAfectado::create([
        'entrada_type' => OrdenCompra::class,
        'entrada_id' => $oc->id,
        'obra_rubro_id' => $obraRubro->id,
        'monto' => 5000,
        'tipo_movimiento' => 'cargo',
        'estatus' => RubroAfectadoEstatus::Aplicado,
        'fecha_aplicacion' => now(),
    ]);

    return compact('requisicion', 'obraRubro', 'oc', 'factura', 'entrega');
}

describe('costos:borrar-requisicion', function () {
    test('folio inexistente falla sin borrar nada', function () {
        $this->artisan('costos:borrar-requisicion', ['folio' => 'NO-EXISTE'])
            ->assertFailed();
    });

    test('dry-run reporta pero no borra', function () {
        $datos = armarRequisicionCompleta();

        $this->artisan('costos:borrar-requisicion', ['folio' => 'REQ-2601-0001'])
            ->assertSuccessful();

        $this->assertDatabaseHas('costos_requisiciones', ['id' => $datos['requisicion']->id]);
        $this->assertDatabaseHas('costos_ordenes_compra', ['id' => $datos['oc']->id]);
        $this->assertDatabaseHas('costos_facturas', ['id' => $datos['factura']->id]);
        $this->assertDatabaseHas('costos_entregas', ['id' => $datos['entrega']->id]);
    });

    test('--force borra la requisición y todo su árbol', function () {
        $datos = armarRequisicionCompleta();
        $oc = $datos['oc'];
        $factura = $datos['factura'];
        $entrega = $datos['entrega'];

        $this->artisan('costos:borrar-requisicion', ['folio' => 'REQ-2601-0001', '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('costos_requisiciones', ['id' => $datos['requisicion']->id]);
        $this->assertDatabaseMissing('costos_requisicion_detalle', ['requisicion_id' => $datos['requisicion']->id]);
        $this->assertDatabaseMissing('costos_ordenes_compra', ['id' => $oc->id]);
        $this->assertDatabaseMissing('costos_ordenes_compra_detalle', ['orden_compra_id' => $oc->id]);
        $this->assertDatabaseMissing('costos_facturas', ['id' => $factura->id]);
        $this->assertDatabaseMissing('costos_factura_detalle', ['factura_id' => $factura->id]);
        $this->assertDatabaseMissing('costos_entregas', ['id' => $entrega->id]);
        $this->assertDatabaseMissing('costos_entrega_detalle', ['entrega_id' => $entrega->id]);
        $this->assertDatabaseMissing('costos_rubros_afectados', ['entrada_id' => $oc->id, 'entrada_type' => OrdenCompra::class]);
    });

    test('--force revierte el presupuesto acumulado del rubro', function () {
        $datos = armarRequisicionCompleta();

        $this->artisan('costos:borrar-requisicion', ['folio' => 'REQ-2601-0001', '--force' => true])
            ->assertSuccessful();

        expect((float) $datos['obraRubro']->fresh()->acumulado)->toBe(0.0);
    });

    test('la OC generada de otra requisición no se toca', function () {
        armarRequisicionCompleta();

        $otraReq = Requisicion::factory()->create(['folio' => 'REQ-2601-0002']);
        $otraOc = OrdenCompra::factory()->create(['requisicion_id' => $otraReq->id]);

        $this->artisan('costos:borrar-requisicion', ['folio' => 'REQ-2601-0001', '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('costos_requisiciones', ['id' => $otraReq->id]);
        $this->assertDatabaseHas('costos_ordenes_compra', ['id' => $otraOc->id]);
    });
});
