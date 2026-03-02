<?php

use App\Models\Infra\Bomba;
use App\Models\Infra\Compresor;
use App\Models\Infra\Ptar;
use App\Models\Infra\Tanque;
use App\Models\Infra\Transformador;
use App\Services\Infra\DashboardService;

beforeEach(function () {
    $this->service = new DashboardService;
});

describe('dashboard service - all null', function () {
    test('all null returns all estados null', function () {
        $result = $this->service->evaluarEstados(null, null, null, null, null);

        expect($result['compresores'])->toHaveCount(3)
            ->and($result['compresores'][0]['estado'])->toBeNull()
            ->and($result['bombas'])->toHaveCount(5)
            ->and($result['bombas'][0]['estado'])->toBeNull()
            ->and($result['tanques'])->toHaveCount(4)
            ->and($result['tanques'][0]['estado'])->toBeNull()
            ->and($result['ptar'])->toHaveCount(1)
            ->and($result['ptar'][0]['estado'])->toBeNull()
            ->and($result['transformadores'])->toHaveCount(3)
            ->and($result['transformadores'][0]['estado'])->toBeNull();
    });
});

describe('dashboard service - compresores', function () {
    test('compresor ON + presion in range = true', function () {
        $c = Compresor::factory()->create([
            'compresor_1_status' => true,
            'compresor_1_presion_aire' => 120,
            'compresor_2_status' => true,
            'compresor_2_presion_aire' => 116,
            'compresor_3_status' => true,
            'compresor_3_presion_aire' => 124,
        ]);

        $result = $this->service->evaluarEstados($c, null, null, null, null);

        expect($result['compresores'][0]['estado'])->toBeTrue()
            ->and($result['compresores'][1]['estado'])->toBeTrue()
            ->and($result['compresores'][2]['estado'])->toBeTrue();
    });

    test('compresor ON + presion out of range = false', function () {
        $c = Compresor::factory()->create([
            'compresor_1_status' => true,
            'compresor_1_presion_aire' => 115,
            'compresor_2_status' => true,
            'compresor_2_presion_aire' => 125,
            'compresor_3_status' => true,
            'compresor_3_presion_aire' => 100,
        ]);

        $result = $this->service->evaluarEstados($c, null, null, null, null);

        expect($result['compresores'][0]['estado'])->toBeFalse()
            ->and($result['compresores'][1]['estado'])->toBeFalse()
            ->and($result['compresores'][2]['estado'])->toBeFalse();
    });

    test('compresor OFF = false', function () {
        $c = Compresor::factory()->create([
            'compresor_1_status' => false,
            'compresor_1_presion_aire' => 120,
            'compresor_2_status' => false,
            'compresor_3_status' => false,
        ]);

        $result = $this->service->evaluarEstados($c, null, null, null, null);

        expect($result['compresores'][0]['estado'])->toBeFalse()
            ->and($result['compresores'][1]['estado'])->toBeFalse()
            ->and($result['compresores'][2]['estado'])->toBeFalse();
    });
});

describe('dashboard service - bombas', function () {
    test('all bomb groups OK', function () {
        $b = Bomba::factory()->create([
            'bomba_posos_1' => true,
            'bomba_posos_2' => true,
            'bomba_planta_1' => true,
            'bomba_planta_2' => true,
            'bomba_planta_3' => true,
            'nivel_salmuera' => 50,
            'nivel_tinaco' => 60,
            'nivel_sisterna' => 50,
            'presion_tuberia' => 45,
            'nivel_hipoclorito' => 50,
            'bomba_jockey' => true,
        ]);

        $result = $this->service->evaluarEstados(null, $b, null, null, null);

        expect($result['bombas'][0]['estado'])->toBeTrue() // pozos
            ->and($result['bombas'][1]['estado'])->toBeTrue() // planta
            ->and($result['bombas'][2]['estado'])->toBeTrue() // purificada
            ->and($result['bombas'][3]['estado'])->toBeTrue() // potable
            ->and($result['bombas'][4]['estado'])->toBeTrue(); // incendios
    });

    test('pozos fails when one pump is off', function () {
        $b = Bomba::factory()->create([
            'bomba_posos_1' => true,
            'bomba_posos_2' => false,
        ]);

        $result = $this->service->evaluarEstados(null, $b, null, null, null);

        expect($result['bombas'][0]['estado'])->toBeFalse();
    });

    test('planta fails when one pump is off', function () {
        $b = Bomba::factory()->create([
            'bomba_planta_1' => true,
            'bomba_planta_2' => false,
            'bomba_planta_3' => true,
        ]);

        $result = $this->service->evaluarEstados(null, $b, null, null, null);

        expect($result['bombas'][1]['estado'])->toBeFalse();
    });

    test('purificada fails when salmuera out of range', function () {
        $b = Bomba::factory()->create([
            'nivel_salmuera' => 15,
            'nivel_tinaco' => 60,
        ]);

        $result = $this->service->evaluarEstados(null, $b, null, null, null);

        expect($result['bombas'][2]['estado'])->toBeFalse();
    });

    test('potable fails when presion_tuberia out of range', function () {
        $b = Bomba::factory()->create([
            'nivel_sisterna' => 50,
            'presion_tuberia' => 55,
            'nivel_hipoclorito' => 50,
        ]);

        $result = $this->service->evaluarEstados(null, $b, null, null, null);

        expect($result['bombas'][3]['estado'])->toBeFalse();
    });

    test('incendios fails when jockey off', function () {
        $b = Bomba::factory()->create([
            'bomba_jockey' => false,
        ]);

        $result = $this->service->evaluarEstados(null, $b, null, null, null);

        expect($result['bombas'][4]['estado'])->toBeFalse();
    });
});

describe('dashboard service - tanques', function () {
    test('O2/Ar/CO2 use presion_sistema, LP uses nivel_tanque', function () {
        $t = Tanque::factory()->create([
            'presion_sistema_oxigeno' => 250,
            'presion_sistema_argon' => 250,
            'nivel_tanque_lp' => 50,
            'presion_sistema_co2' => 250,
        ]);

        $result = $this->service->evaluarEstados(null, null, $t, null, null);

        expect($result['tanques'][0]['estado'])->toBeTrue() // O2
            ->and($result['tanques'][1]['estado'])->toBeTrue() // Ar
            ->and($result['tanques'][2]['estado'])->toBeTrue() // LP
            ->and($result['tanques'][3]['estado'])->toBeTrue(); // CO2
    });

    test('tanques out of range = false', function () {
        $t = Tanque::factory()->create([
            'presion_sistema_oxigeno' => 150,
            'presion_sistema_argon' => 300,
            'nivel_tanque_lp' => 10,
            'presion_sistema_co2' => 300,
        ]);

        $result = $this->service->evaluarEstados(null, null, $t, null, null);

        expect($result['tanques'][0]['estado'])->toBeFalse()
            ->and($result['tanques'][1]['estado'])->toBeFalse()
            ->and($result['tanques'][2]['estado'])->toBeFalse()
            ->and($result['tanques'][3]['estado'])->toBeFalse();
    });
});

describe('dashboard service - ptar', function () {
    test('all conditions met = true', function () {
        $p = Ptar::factory()->create([
            'soplador_activa' => true,
            'bomba_activa' => true,
            'trampa_solida' => true,
            'nivel_cloro' => 50,
        ]);

        $result = $this->service->evaluarEstados(null, null, null, $p, null);

        expect($result['ptar'][0]['estado'])->toBeTrue();
    });

    test('ptar fails when any condition not met', function () {
        $p = Ptar::factory()->create([
            'soplador_activa' => true,
            'bomba_activa' => false,
            'trampa_solida' => true,
            'nivel_cloro' => 50,
        ]);

        $result = $this->service->evaluarEstados(null, null, null, $p, null);

        expect($result['ptar'][0]['estado'])->toBeFalse();
    });

    test('ptar fails when cloro out of range', function () {
        $p = Ptar::factory()->create([
            'soplador_activa' => true,
            'bomba_activa' => true,
            'trampa_solida' => true,
            'nivel_cloro' => 20,
        ]);

        $result = $this->service->evaluarEstados(null, null, null, $p, null);

        expect($result['ptar'][0]['estado'])->toBeFalse();
    });
});

describe('dashboard service - transformadores', function () {
    test('transformador without voltage = true for all lines', function () {
        $t = Transformador::factory()->create();

        $result = $this->service->evaluarEstados(null, null, null, null, $t);

        expect($result['transformadores'][0]['estado'])->toBeTrue()
            ->and($result['transformadores'][1]['estado'])->toBeTrue()
            ->and($result['transformadores'][2]['estado'])->toBeTrue();
    });

    test('transformador with voltage in range (120-130) = true', function () {
        $t = Transformador::factory()->create([
            'voltaje_a' => 125,
            'voltaje_b' => 120,
            'voltaje_c' => 130,
        ]);

        $result = $this->service->evaluarEstados(null, null, null, null, $t);

        expect($result['transformadores'][0]['estado'])->toBeTrue()
            ->and($result['transformadores'][1]['estado'])->toBeTrue()
            ->and($result['transformadores'][2]['estado'])->toBeTrue();
    });

    test('transformador with voltage out of range = false', function () {
        $t = Transformador::factory()->create([
            'voltaje_a' => 115,
            'voltaje_b' => 135,
            'voltaje_c' => 110,
        ]);

        $result = $this->service->evaluarEstados(null, null, null, null, $t);

        expect($result['transformadores'][0]['estado'])->toBeFalse()
            ->and($result['transformadores'][1]['estado'])->toBeFalse()
            ->and($result['transformadores'][2]['estado'])->toBeFalse();
    });
});
