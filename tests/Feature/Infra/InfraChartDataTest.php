<?php

use App\Models\Infra\Bomba;
use App\Models\Infra\Compresor;
use App\Models\Infra\Tanque;
use App\Models\Infra\Transformador;
use Carbon\Carbon;

describe('compresor horas laboradas mensuales', function () {
    test('calculates delta of tiempo_marcha per month', function () {
        $now = Carbon::create(2026, 3, 1);

        Compresor::factory()->create([
            'compresor_1_tiempo_marcha' => 100,
            'compresor_2_tiempo_marcha' => 200,
            'compresor_3_tiempo_marcha' => 300,
            'created_at' => $now->copy()->startOfMonth(),
        ]);

        Compresor::factory()->create([
            'compresor_1_tiempo_marcha' => 150,
            'compresor_2_tiempo_marcha' => 260,
            'compresor_3_tiempo_marcha' => 320,
            'created_at' => $now->copy()->endOfMonth(),
        ]);

        $result = Compresor::horasLaboradasMensuales(2026);

        // March is index 2 (mes = '03')
        expect($result[2]['c1'])->toBe(50.0)
            ->and($result[2]['c2'])->toBe(60.0)
            ->and($result[2]['c3'])->toBe(20.0);
    });

    test('empty months return zero', function () {
        $result = Compresor::horasLaboradasMensuales(2026);

        expect($result)->toHaveCount(12)
            ->and($result[0]['c1'])->toBe(0)
            ->and($result[11]['c3'])->toBe(0);
    });
});

describe('bomba estadisticas mensuales', function () {
    test('calculates avg, max, min and stddev', function () {
        $fecha = Carbon::create(2026, 5, 1);

        Bomba::factory()->create(['presion_tuberia' => 40, 'created_at' => $fecha->copy()->addDays(1)]);
        Bomba::factory()->create(['presion_tuberia' => 50, 'created_at' => $fecha->copy()->addDays(2)]);
        Bomba::factory()->create(['presion_tuberia' => 60, 'created_at' => $fecha->copy()->addDays(3)]);

        $result = Bomba::estadisticasMensuales(2026);

        // May is index 4 (mes = '05')
        expect($result[4]['avg_presion'])->toBe(50.0)
            ->and($result[4]['max_presion'])->toBe(60.0)
            ->and($result[4]['min_presion'])->toBe(40.0)
            ->and($result[4]['stddev'])->toBeGreaterThan(0);
    });

    test('empty months return zero', function () {
        $result = Bomba::estadisticasMensuales(2026);

        expect($result)->toHaveCount(12)
            ->and($result[0]['avg_presion'])->toBe(0);
    });

    test('multiple records per day are averaged before monthly aggregation', function () {
        $fecha = Carbon::create(2026, 5, 10);

        // Day 1: two readings (40, 60) -> daily avg = 50
        Bomba::factory()->create(['presion_tuberia' => 40, 'created_at' => $fecha->copy()->setTime(8, 0)]);
        Bomba::factory()->create(['presion_tuberia' => 60, 'created_at' => $fecha->copy()->setTime(14, 0)]);

        // Day 2: two readings (30, 50) -> daily avg = 40
        Bomba::factory()->create(['presion_tuberia' => 30, 'created_at' => $fecha->copy()->addDay()->setTime(8, 0)]);
        Bomba::factory()->create(['presion_tuberia' => 50, 'created_at' => $fecha->copy()->addDay()->setTime(14, 0)]);

        $result = Bomba::estadisticasMensuales(2026);

        // Monthly avg from daily avgs: (50 + 40) / 2 = 45
        expect($result[4]['avg_presion'])->toBe(45.0)
            ->and($result[4]['max_presion'])->toBe(50.0) // max of daily avgs
            ->and($result[4]['min_presion'])->toBe(40.0); // min of daily avgs
    });
});

describe('transformador consumos mensuales', function () {
    test('sums totals by month', function () {
        $fecha = Carbon::create(2026, 1, 10);

        Transformador::factory()->create([
            'total_1' => 100, 'total_5' => 200, 'lectura_5y5' => 50,
            'created_at' => $fecha,
        ]);
        Transformador::factory()->create([
            'total_1' => 150, 'total_5' => 300, 'lectura_5y5' => 75,
            'created_at' => $fecha->copy()->addDays(5),
        ]);

        $result = Transformador::consumosMensuales(2026);

        // January is index 0 (mes = '01')
        expect($result[0]['total_1'])->toBe(250.0)
            ->and($result[0]['total_5'])->toBe(500.0)
            ->and($result[0]['lectura_5y5'])->toBe(125.0);
    });

    test('empty months return zero', function () {
        $result = Transformador::consumosMensuales(2026);

        expect($result)->toHaveCount(12)
            ->and($result[5]['total_1'])->toBe(0);
    });
});

describe('tanque consumo mensual acumulado', function () {
    test('counts only drops in kg as consumption', function () {
        $base = Carbon::create(2026, 2, 1);

        // Day 1: initial reading
        Tanque::factory()->create([
            'kg_tanque_oxigeno' => 1000, 'kg_tanque_argon' => 500,
            'kg_tanque_co2' => 300, 'kg_tanque_lp' => 200,
            'created_at' => $base,
        ]);

        // Day 2: consumption (kg drops)
        Tanque::factory()->create([
            'kg_tanque_oxigeno' => 900, 'kg_tanque_argon' => 450,
            'kg_tanque_co2' => 280, 'kg_tanque_lp' => 180,
            'created_at' => $base->copy()->addDay(),
        ]);

        // Day 3: refill (kg goes up - should be ignored)
        Tanque::factory()->create([
            'kg_tanque_oxigeno' => 2000, 'kg_tanque_argon' => 1000,
            'kg_tanque_co2' => 600, 'kg_tanque_lp' => 400,
            'created_at' => $base->copy()->addDays(2),
        ]);

        // Day 4: more consumption
        Tanque::factory()->create([
            'kg_tanque_oxigeno' => 1800, 'kg_tanque_argon' => 950,
            'kg_tanque_co2' => 550, 'kg_tanque_lp' => 370,
            'created_at' => $base->copy()->addDays(3),
        ]);

        $result = Tanque::consumoMensualAcumulado(2026);

        // February is index 1 (mes = '02')
        // O2: 100 (1000->900) + 200 (2000->1800) = 300
        expect($result[1]['oxigeno'])->toBe(300.0)
            // Ar: 50 (500->450) + 50 (1000->950) = 100
            ->and($result[1]['argon'])->toBe(100.0)
            // CO2: 20 (300->280) + 50 (600->550) = 70
            ->and($result[1]['co2'])->toBe(70.0)
            // LP: 20 (200->180) + 30 (400->370) = 50
            ->and($result[1]['lp'])->toBe(50.0);
    });

    test('acumulado is progressive across months', function () {
        // Create consumption in Jan
        Tanque::factory()->create([
            'kg_tanque_oxigeno' => 1000, 'kg_tanque_argon' => 500,
            'kg_tanque_co2' => 300, 'kg_tanque_lp' => 200,
            'created_at' => Carbon::create(2026, 1, 1),
        ]);
        Tanque::factory()->create([
            'kg_tanque_oxigeno' => 900, 'kg_tanque_argon' => 450,
            'kg_tanque_co2' => 280, 'kg_tanque_lp' => 180,
            'created_at' => Carbon::create(2026, 1, 15),
        ]);

        // Create consumption in Feb
        Tanque::factory()->create([
            'kg_tanque_oxigeno' => 800, 'kg_tanque_argon' => 400,
            'kg_tanque_co2' => 260, 'kg_tanque_lp' => 160,
            'created_at' => Carbon::create(2026, 2, 1),
        ]);
        Tanque::factory()->create([
            'kg_tanque_oxigeno' => 700, 'kg_tanque_argon' => 350,
            'kg_tanque_co2' => 240, 'kg_tanque_lp' => 140,
            'created_at' => Carbon::create(2026, 2, 15),
        ]);

        $result = Tanque::consumoMensualAcumulado(2026);

        // Jan: O2=100, Feb: O2=100+100=200 (900->800 is a drop across months too, counts in Feb)
        // Actually: Jan has drop 1000->900 = 100. Then Feb starts at 800 (which is less than 900, so counts as consumption = 100).
        // Then 800->700 = another 100. So Feb total = 200.
        expect($result[0]['oxigeno'])->toBe(100.0) // Jan
            ->and($result[0]['oxigeno_acum'])->toBe(100.0)
            ->and($result[1]['oxigeno'])->toBe(200.0) // Feb (900->800 + 800->700)
            ->and($result[1]['oxigeno_acum'])->toBe(300.0);
    });
});
