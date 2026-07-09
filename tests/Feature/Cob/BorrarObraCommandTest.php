<?php

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Cob\Anticipo;
use App\Models\Cob\Estimacion;
use App\Models\Cob\EstimacionPago;
use App\Models\Cob\Partida;
use App\Models\Concepto;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\RubroAfectado;
use App\Models\Obra;
use App\Models\Prod\GrupoPrecio;
use App\Models\Proyecto;

/**
 * Arma una obra con su árbol completo: cobranza (partida, estimación + pago,
 * anticipo), producción (concepto, grupo de precio), presupuesto de costos
 * (rubro + afectación) y una OC que la referencia (debe sobrevivir).
 *
 * @return array<string, mixed>
 */
function armarObraCompleta(): array
{
    $proyecto = Proyecto::factory()->create();
    $obra = Obra::factory()->create(['proyecto_id' => $proyecto->id]);

    $partida = Partida::factory()->create(['obra_id' => $obra->id]);
    $estimacion = Estimacion::factory()->create(['obra_id' => $obra->id]);
    $pago = EstimacionPago::factory()->create(['estimacion_id' => $estimacion->id]);
    $anticipo = Anticipo::factory()->create(['obra_id' => $obra->id]);

    $concepto = Concepto::factory()->create(['obra_id' => $obra->id]);
    $grupoPrecio = GrupoPrecio::factory()->create(['obra_id' => $obra->id]);

    $presupuesto = Presupuesto::factory()->create([
        'presupuestable_type' => Obra::class,
        'presupuestable_id' => $obra->id,
    ]);
    $obraRubro = ObraRubro::factory()->create(['obra_id' => $obra->id, 'presupuesto_id' => $presupuesto->id]);

    $oc = OrdenCompra::factory()->create(['obra_id' => $obra->id]);
    RubroAfectado::create([
        'entrada_type' => OrdenCompra::class,
        'entrada_id' => $oc->id,
        'obra_rubro_id' => $obraRubro->id,
        'monto' => 1000,
        'tipo_movimiento' => 'cargo',
        'estatus' => RubroAfectadoEstatus::Aplicado,
        'fecha_aplicacion' => now(),
    ]);

    return compact('proyecto', 'obra', 'partida', 'estimacion', 'pago', 'anticipo', 'concepto', 'grupoPrecio', 'presupuesto', 'obraRubro', 'oc');
}

describe('cob:borrar-obra', function () {
    test('id inexistente falla sin borrar nada', function () {
        $obra = Obra::factory()->create();

        $this->artisan('cob:borrar-obra', ['id' => [$obra->id, 999999]])
            ->assertFailed();

        $this->assertDatabaseHas('obras', ['id' => $obra->id]);
    });

    test('dry-run reporta pero no borra', function () {
        $datos = armarObraCompleta();

        $this->artisan('cob:borrar-obra', ['id' => [$datos['obra']->id]])
            ->assertSuccessful();

        $this->assertDatabaseHas('obras', ['id' => $datos['obra']->id]);
        $this->assertDatabaseHas('cob_estimaciones', ['id' => $datos['estimacion']->id]);
        $this->assertDatabaseHas('costos_obra_rubros', ['id' => $datos['obraRubro']->id]);
    });

    test('--force borra la obra y todo su árbol', function () {
        $datos = armarObraCompleta();

        $this->artisan('cob:borrar-obra', ['id' => [$datos['obra']->id], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('obras', ['id' => $datos['obra']->id]);
        // Cobranza
        $this->assertDatabaseMissing('cob_partidas', ['id' => $datos['partida']->id]);
        $this->assertDatabaseMissing('cob_estimaciones', ['id' => $datos['estimacion']->id]);
        $this->assertDatabaseMissing('cob_estimaciones_pagos', ['id' => $datos['pago']->id]);
        $this->assertDatabaseMissing('cob_anticipos', ['id' => $datos['anticipo']->id]);
        // Producción
        $this->assertDatabaseMissing('conceptos', ['id' => $datos['concepto']->id]);
        $this->assertDatabaseMissing('prod_grupos_precio', ['id' => $datos['grupoPrecio']->id]);
        // Costos (presupuesto)
        $this->assertDatabaseMissing('costos_presupuestos', ['id' => $datos['presupuesto']->id]);
        $this->assertDatabaseMissing('costos_obra_rubros', ['id' => $datos['obraRubro']->id]);
        $this->assertDatabaseMissing('costos_rubros_afectados', ['obra_rubro_id' => $datos['obraRubro']->id]);
    });

    test('el proyecto se conserva', function () {
        $datos = armarObraCompleta();

        $this->artisan('cob:borrar-obra', ['id' => [$datos['obra']->id], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('proyectos', ['id' => $datos['proyecto']->id]);
    });

    test('la OC de costos sobrevive con obra_id nulo', function () {
        $datos = armarObraCompleta();

        $this->artisan('cob:borrar-obra', ['id' => [$datos['obra']->id], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('costos_ordenes_compra', ['id' => $datos['oc']->id, 'obra_id' => null]);
    });

    test('otra obra no se toca', function () {
        $datos = armarObraCompleta();
        $otra = armarObraCompleta();

        $this->artisan('cob:borrar-obra', ['id' => [$datos['obra']->id], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('obras', ['id' => $otra['obra']->id]);
        $this->assertDatabaseHas('cob_estimaciones', ['id' => $otra['estimacion']->id]);
        $this->assertDatabaseHas('costos_obra_rubros', ['id' => $otra['obraRubro']->id]);
    });
});
