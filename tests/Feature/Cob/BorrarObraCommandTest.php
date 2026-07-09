<?php

use App\Models\Cob\Anticipo;
use App\Models\Cob\Estimacion;
use App\Models\Cob\EstimacionPago;
use App\Models\Cob\Partida;
use App\Models\Concepto;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Presupuesto;
use App\Models\Obra;
use App\Models\Prod\GrupoPrecio;
use App\Models\Proyecto;

/**
 * Arma una obra con árbol de cobranza y producción, SIN presupuesto de costos
 * (para que el comando la pueda borrar). Incluye una OC que debe sobrevivir.
 *
 * @return array<string, mixed>
 */
function armarObraSinPresupuesto(): array
{
    $proyecto = Proyecto::factory()->create();
    $obra = Obra::factory()->create(['proyecto_id' => $proyecto->id]);

    $partida = Partida::factory()->create(['obra_id' => $obra->id]);
    $estimacion = Estimacion::factory()->create(['obra_id' => $obra->id]);
    $pago = EstimacionPago::factory()->create(['estimacion_id' => $estimacion->id]);
    $anticipo = Anticipo::factory()->create(['obra_id' => $obra->id]);

    $concepto = Concepto::factory()->create(['obra_id' => $obra->id]);
    $grupoPrecio = GrupoPrecio::factory()->create(['obra_id' => $obra->id]);

    $oc = OrdenCompra::factory()->create(['obra_id' => $obra->id]);

    return compact('proyecto', 'obra', 'partida', 'estimacion', 'pago', 'anticipo', 'concepto', 'grupoPrecio', 'oc');
}

describe('cob:borrar-obra', function () {
    test('id inexistente falla sin borrar nada', function () {
        $obra = Obra::factory()->create();

        $this->artisan('cob:borrar-obra', ['id' => [$obra->id, 999999]])
            ->assertFailed();

        $this->assertDatabaseHas('obras', ['id' => $obra->id]);
    });

    test('rechaza una obra con presupuesto propio ligado', function () {
        $obra = Obra::factory()->create();
        Presupuesto::factory()->create([
            'presupuestable_type' => Obra::class,
            'presupuestable_id' => $obra->id,
        ]);

        $this->artisan('cob:borrar-obra', ['id' => [$obra->id], '--force' => true])
            ->assertFailed();

        $this->assertDatabaseHas('obras', ['id' => $obra->id]);
    });

    test('rechaza una obra con centros de costo (obra_rubros) ligados', function () {
        $obra = Obra::factory()->create();
        ObraRubro::factory()->create(['obra_id' => $obra->id]);

        $this->artisan('cob:borrar-obra', ['id' => [$obra->id], '--force' => true])
            ->assertFailed();

        $this->assertDatabaseHas('obras', ['id' => $obra->id]);
    });

    test('rechaza una obra cuya partida adicional tiene presupuesto', function () {
        $obra = Obra::factory()->create();
        $partida = Partida::factory()->create(['obra_id' => $obra->id]);
        Presupuesto::factory()->create([
            'presupuestable_type' => Partida::class,
            'presupuestable_id' => $partida->id,
        ]);

        $this->artisan('cob:borrar-obra', ['id' => [$obra->id], '--force' => true])
            ->assertFailed();

        $this->assertDatabaseHas('obras', ['id' => $obra->id]);
    });

    test('dry-run reporta pero no borra', function () {
        $datos = armarObraSinPresupuesto();

        $this->artisan('cob:borrar-obra', ['id' => [$datos['obra']->id]])
            ->assertSuccessful();

        $this->assertDatabaseHas('obras', ['id' => $datos['obra']->id]);
        $this->assertDatabaseHas('cob_estimaciones', ['id' => $datos['estimacion']->id]);
    });

    test('--force borra la obra y su árbol de cobranza y producción', function () {
        $datos = armarObraSinPresupuesto();

        $this->artisan('cob:borrar-obra', ['id' => [$datos['obra']->id], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('obras', ['id' => $datos['obra']->id]);
        $this->assertDatabaseMissing('cob_partidas', ['id' => $datos['partida']->id]);
        $this->assertDatabaseMissing('cob_estimaciones', ['id' => $datos['estimacion']->id]);
        $this->assertDatabaseMissing('cob_estimaciones_pagos', ['id' => $datos['pago']->id]);
        $this->assertDatabaseMissing('cob_anticipos', ['id' => $datos['anticipo']->id]);
        $this->assertDatabaseMissing('conceptos', ['id' => $datos['concepto']->id]);
        $this->assertDatabaseMissing('prod_grupos_precio', ['id' => $datos['grupoPrecio']->id]);
    });

    test('el proyecto se conserva', function () {
        $datos = armarObraSinPresupuesto();

        $this->artisan('cob:borrar-obra', ['id' => [$datos['obra']->id], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('proyectos', ['id' => $datos['proyecto']->id]);
    });

    test('la OC de costos sobrevive con obra_id nulo', function () {
        $datos = armarObraSinPresupuesto();

        $this->artisan('cob:borrar-obra', ['id' => [$datos['obra']->id], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('costos_ordenes_compra', ['id' => $datos['oc']->id, 'obra_id' => null]);
    });

    test('otra obra no se toca', function () {
        $datos = armarObraSinPresupuesto();
        $otra = armarObraSinPresupuesto();

        $this->artisan('cob:borrar-obra', ['id' => [$datos['obra']->id], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('obras', ['id' => $otra['obra']->id]);
        $this->assertDatabaseHas('cob_estimaciones', ['id' => $otra['estimacion']->id]);
    });
});
