<?php

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\RubroAfectado;

/**
 * Arma un presupuesto con dos centros de costos (obra_rubros), uno de ellos con
 * un rubro afectado vivo apuntando a una OC.
 *
 * @return array{presupuesto: Presupuesto, obraRubro: ObraRubro, oc: OrdenCompra}
 */
function armarPresupuestoConAfectacion(): array
{
    $presupuesto = Presupuesto::factory()->create();
    $obraRubro = ObraRubro::factory()->create(['presupuesto_id' => $presupuesto->id, 'acumulado' => 5000]);
    ObraRubro::factory()->create(['presupuesto_id' => $presupuesto->id]);

    $oc = OrdenCompra::factory()->create();
    RubroAfectado::create([
        'entrada_type' => OrdenCompra::class,
        'entrada_id' => $oc->id,
        'obra_rubro_id' => $obraRubro->id,
        'monto' => 5000,
        'tipo_movimiento' => 'cargo',
        'estatus' => RubroAfectadoEstatus::Aplicado,
        'fecha_aplicacion' => now(),
    ]);

    return compact('presupuesto', 'obraRubro', 'oc');
}

describe('costos:borrar-presupuesto', function () {
    test('id inexistente falla sin borrar nada', function () {
        $presupuesto = Presupuesto::factory()->create();

        $this->artisan('costos:borrar-presupuesto', ['id' => [$presupuesto->id, 999999]])
            ->assertFailed();

        $this->assertDatabaseHas('costos_presupuestos', ['id' => $presupuesto->id]);
    });

    test('dry-run reporta pero no borra', function () {
        $datos = armarPresupuestoConAfectacion();

        $this->artisan('costos:borrar-presupuesto', ['id' => [$datos['presupuesto']->id]])
            ->assertSuccessful();

        $this->assertDatabaseHas('costos_presupuestos', ['id' => $datos['presupuesto']->id]);
        $this->assertDatabaseHas('costos_obra_rubros', ['id' => $datos['obraRubro']->id]);
        $this->assertDatabaseHas('costos_rubros_afectados', ['obra_rubro_id' => $datos['obraRubro']->id]);
    });

    test('--force borra el presupuesto, sus centros de costos y rubros afectados', function () {
        $datos = armarPresupuestoConAfectacion();

        $this->artisan('costos:borrar-presupuesto', ['id' => [$datos['presupuesto']->id], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('costos_presupuestos', ['id' => $datos['presupuesto']->id]);
        $this->assertDatabaseMissing('costos_obra_rubros', ['presupuesto_id' => $datos['presupuesto']->id]);
        $this->assertDatabaseMissing('costos_rubros_afectados', ['obra_rubro_id' => $datos['obraRubro']->id]);
    });

    test('la OC que afectaba el presupuesto sobrevive al borrado', function () {
        $datos = armarPresupuestoConAfectacion();

        $this->artisan('costos:borrar-presupuesto', ['id' => [$datos['presupuesto']->id], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('costos_ordenes_compra', ['id' => $datos['oc']->id]);
    });

    test('otro presupuesto no se toca', function () {
        $datos = armarPresupuestoConAfectacion();
        $otro = Presupuesto::factory()->create();
        $otroRubro = ObraRubro::factory()->create(['presupuesto_id' => $otro->id]);

        $this->artisan('costos:borrar-presupuesto', ['id' => [$datos['presupuesto']->id], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('costos_presupuestos', ['id' => $otro->id]);
        $this->assertDatabaseHas('costos_obra_rubros', ['id' => $otroRubro->id]);
    });
});
