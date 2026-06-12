<?php

use App\Enums\Cotiz\MetodoFleteEstandar;
use App\Models\Cotiz\FaseMontaje;
use App\Models\Cotiz\KilosRealesCategoria;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraCuadrillaGlobal;
use App\Models\Cotiz\ObraFleteEstandar;
use App\Models\Cotiz\ObraFleteViatico;
use App\Models\Cotiz\PersonalCategoria;
use App\Models\Cotiz\SeccionFaseRendimiento;
use App\Models\Cotiz\SeccionMontaje;
use App\Models\Cotiz\SeccionPersonal;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaCategoriaKilos;
use App\Models\Cotiz\TarjetaEstructura;
use App\Models\Cotiz\TarjetaKilosReal;
use App\Models\Cotiz\TarjetaRegistro;
use App\Services\Cotiz\CuadrillaGlobalDerivations;
use App\Services\Cotiz\FleteEstandarDerivations;
use App\Services\Cotiz\FletesViaticosSubtotales;
use App\Services\Cotiz\MontajeDerivations;

describe('MontajeDerivations', function () {
    test('días por fase = Σ(cantidad/rendimiento) con guarda contra rendimiento 0', function () {
        $seccion = SeccionMontaje::factory()->create();
        $fase = FaseMontaje::factory()->create();
        SeccionFaseRendimiento::factory()->create(['seccion_id' => $seccion->id, 'fase_id' => $fase->id, 'cantidad' => 10, 'rendimiento' => 5]);
        SeccionFaseRendimiento::factory()->create(['seccion_id' => $seccion->id, 'fase_id' => $fase->id, 'cantidad' => 6, 'rendimiento' => 0]); // → 0, no error
        $seccion->load('rendimientos');

        $dias = app(MontajeDerivations::class)->diasPorFase($seccion);

        expect($dias[$fase->id])->toEqualWithDelta(2.0, 1e-9);
    });

    test('nómina, importe por fase y total con factor contratista', function () {
        $obra = Obra::factory()->create(['factor_contratista' => 1.15]);
        $seccion = SeccionMontaje::factory()->create(['obra_id' => $obra->id]);
        $fase = FaseMontaje::factory()->create();
        $catA = PersonalCategoria::factory()->create(['sueldo_semanal' => 1000]);
        $catB = PersonalCategoria::factory()->create(['sueldo_semanal' => 2000]);

        SeccionPersonal::factory()->create(['seccion_id' => $seccion->id, 'fase_id' => $fase->id, 'categoria_id' => $catA->id, 'cantidad' => 2]);
        SeccionPersonal::factory()->create(['seccion_id' => $seccion->id, 'fase_id' => $fase->id, 'categoria_id' => $catB->id, 'cantidad' => 1]);
        SeccionFaseRendimiento::factory()->create(['seccion_id' => $seccion->id, 'fase_id' => $fase->id, 'cantidad' => 10, 'rendimiento' => 5]);
        $seccion->load(['rendimientos', 'personal.categoria']);

        $svc = app(MontajeDerivations::class);
        $porFase = $svc->importePorFase($seccion);

        // nómina = 2*1000 + 1*2000 = 4000; días = 2; semanas = 2/5.5; importe = 4000 × 2/5.5.
        expect($porFase[$fase->id]['nomina_semanal'])->toEqualWithDelta(4000.0, 1e-9)
            ->and($porFase[$fase->id]['dias'])->toEqualWithDelta(2.0, 1e-9)
            ->and($porFase[$fase->id]['importe'])->toEqualWithDelta(4000.0 * 2 / 5.5, 1e-6);

        $imp = $svc->importeSeccion($seccion, 1.15);
        expect($imp['importe_directo'])->toEqualWithDelta(4000.0 * 2 / 5.5, 1e-6)
            ->and($imp['importe_total'])->toEqualWithDelta(4000.0 * 2 / 5.5 * 1.15, 1e-6);
        expect($svc->semanasSeccion($seccion))->toEqualWithDelta(2 / 5.5, 1e-9);
    });

    test('semanas requeridas y mo_montaje agregan sobre las secciones de la obra', function () {
        $obra = Obra::factory()->create(['factor_contratista' => 1.15]);
        $fase = FaseMontaje::factory()->create();
        $cat = PersonalCategoria::factory()->create(['sueldo_semanal' => 1000]);

        foreach ([['c' => 10, 'r' => 5], ['c' => 11, 'r' => 5.5]] as $datos) {
            $s = SeccionMontaje::factory()->create(['obra_id' => $obra->id]);
            SeccionPersonal::factory()->create(['seccion_id' => $s->id, 'fase_id' => $fase->id, 'categoria_id' => $cat->id, 'cantidad' => 1]);
            SeccionFaseRendimiento::factory()->create(['seccion_id' => $s->id, 'fase_id' => $fase->id, 'cantidad' => $datos['c'], 'rendimiento' => $datos['r']]);
        }
        $obra->load(['seccionesMontaje.rendimientos', 'seccionesMontaje.personal.categoria']);

        $svc = app(MontajeDerivations::class);
        // días: 10/5 = 2 y 11/5.5 = 2 → Σ = 4; semanas = 4/5.5.
        expect($svc->semanasRequeridasObra($obra))->toEqualWithDelta(4 / 5.5, 1e-9);
        // importe por sección = 1000 × días/5.5; total = ×1.15; Σ ambas.
        $esperado = (1000 * 2 / 5.5 + 1000 * 2 / 5.5) * 1.15;
        expect($svc->moMontajeObra($obra, 1.15))->toEqualWithDelta($esperado, 1e-6);
    });
});

describe('CuadrillaGlobalDerivations', function () {
    test('totales × num_grupos y personas para viáticos excluyen al CABO', function () {
        $obra = Obra::factory()->create(['num_grupos' => 2]);
        $cabo = PersonalCategoria::factory()->create(['codigo' => 'CABO', 'sueldo_semanal' => 500]);
        $oficial = PersonalCategoria::factory()->create(['codigo' => 'OFICIAL', 'sueldo_semanal' => 1000]);
        ObraCuadrillaGlobal::factory()->create(['obra_id' => $obra->id, 'categoria_id' => $cabo->id, 'cantidad_por_grupo' => 1]);
        ObraCuadrillaGlobal::factory()->create(['obra_id' => $obra->id, 'categoria_id' => $oficial->id, 'cantidad_por_grupo' => 3]);
        $obra->load('cuadrillaGlobal.categoria');

        $t = app(CuadrillaGlobalDerivations::class)->totales($obra);

        expect($t['personas_totales'])->toBe(8)                       // (1+3) × 2
            ->and($t['nomina_total'])->toEqualWithDelta(7000.0, 1e-9) // (1*500 + 3*1000) × 2
            ->and($t['nomina_con_contratista'])->toEqualWithDelta(8050.0, 1e-9)
            ->and($t['personas_viaticos'])->toBe(6);                  // 8 − (1 cabo × 2 grupos)
    });
});

describe('FleteEstandarDerivations', function () {
    test('volumen por_kg = fijas × (1 + Σ porcentuales) y camiones ROUNDUP', function () {
        $obra = Obra::factory()->create();
        $tarjeta = Tarjeta::factory()->create(['obra_id' => $obra->id]);
        $estructura = TarjetaEstructura::factory()->create(['tarjeta_id' => $tarjeta->id]);
        $catFija = KilosRealesCategoria::factory()->create(['tipo_corte' => 'TIRAS']);
        $catPct = KilosRealesCategoria::factory()->create(['tipo_corte' => 'RAZ']);
        TarjetaCategoriaKilos::factory()->create(['tarjeta_id' => $tarjeta->id, 'categoria_id' => $catFija->id, 'porcentual' => null]);
        TarjetaCategoriaKilos::factory()->create(['tarjeta_id' => $tarjeta->id, 'categoria_id' => $catPct->id, 'porcentual' => 0.1]);
        TarjetaKilosReal::factory()->create(['tarjeta_id' => $tarjeta->id, 'categoria_id' => $catFija->id, 'estructura_id' => $estructura->id, 'kilos' => 1000]);

        $flete = ObraFleteEstandar::factory()->create([
            'obra_id' => $obra->id, 'tarjeta_id' => $tarjeta->id,
            'metodo' => MetodoFleteEstandar::PorKg, 'kg_por_camion' => 15000,
        ]);

        $d = app(FleteEstandarDerivations::class)->derivar($flete);
        // volumen = 1000 × 1.1 = 1100; camiones = roundup(1100/15000, 2) = 0.08.
        expect($d['volumen'])->toEqualWithDelta(1100.0, 1e-9)
            ->and($d['camiones'])->toEqualWithDelta(0.08, 1e-9);
    });

    test('volumen por_piezas = Σ cantidad de registros y override gana', function () {
        $obra = Obra::factory()->create();
        $tarjeta = Tarjeta::factory()->create(['obra_id' => $obra->id]);
        TarjetaRegistro::factory()->create(['tarjeta_id' => $tarjeta->id, 'generadora_registro_id' => null, 'insumo_id' => null, 'cantidad' => 100]);
        TarjetaRegistro::factory()->create(['tarjeta_id' => $tarjeta->id, 'generadora_registro_id' => null, 'insumo_id' => null, 'cantidad' => 200]);

        $flete = ObraFleteEstandar::factory()->create([
            'obra_id' => $obra->id, 'tarjeta_id' => $tarjeta->id,
            'metodo' => MetodoFleteEstandar::PorPiezas, 'ml_por_pza' => 3.05, 'pzas_por_camion' => 400,
        ]);

        $svc = app(FleteEstandarDerivations::class);
        $d = $svc->derivar($flete);
        // volumen = 300; pzas = ceil(300/3.05) = 99; camiones = roundup(99/400, 2) = 0.25.
        expect($d['volumen'])->toEqualWithDelta(300.0, 1e-9)
            ->and($d['pzas'])->toEqualWithDelta(99.0, 1e-9)
            ->and($d['camiones'])->toEqualWithDelta(0.25, 1e-9);

        $flete->update(['volumen_override' => 5000]);
        expect($svc->derivar($flete->fresh())['volumen'])->toEqualWithDelta(5000.0, 1e-9);
    });

    test('camiones agregados por grupo (slug) y total', function () {
        $obra = Obra::factory()->create();
        $tarjeta = Tarjeta::factory()->create(['obra_id' => $obra->id]);
        ObraFleteEstandar::factory()->create([
            'obra_id' => $obra->id, 'tarjeta_id' => $tarjeta->id, 'grupo' => 'Láminas',
            'metodo' => MetodoFleteEstandar::PorKg, 'kg_por_camion' => 15000, 'volumen_override' => 15000,
        ]);

        $ctx = app(FleteEstandarDerivations::class)->camionesPorGrupo($obra->fresh());

        expect($ctx['camiones_total'])->toEqualWithDelta(1.0, 1e-9)
            ->and($ctx['camiones_LAMINAS'])->toEqualWithDelta(1.0, 1e-9);
    });
});

describe('FletesViaticosSubtotales', function () {
    test('subtotal por grupo = Σ(cantidad × p_unit)', function () {
        $obra = Obra::factory()->create();
        ObraFleteViatico::factory()->create(['obra_id' => $obra->id, 'grupo' => 'VIATICOS', 'cantidad' => 2, 'p_unit' => 10]);
        ObraFleteViatico::factory()->create(['obra_id' => $obra->id, 'grupo' => 'VIATICOS', 'cantidad' => 1, 'p_unit' => 5]);
        ObraFleteViatico::factory()->create(['obra_id' => $obra->id, 'grupo' => 'FLETES', 'cantidad' => 5, 'p_unit' => 4]);

        $r = app(FletesViaticosSubtotales::class)->calcular($obra);

        expect($r['subtotales']['VIATICOS'])->toEqualWithDelta(25.0, 1e-9)
            ->and($r['subtotales']['FLETES'])->toEqualWithDelta(20.0, 1e-9)
            ->and($r['total'])->toEqualWithDelta(45.0, 1e-9);
    });
});
