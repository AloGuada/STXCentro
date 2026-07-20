<?php

use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraResumenCeldaOverride;
use App\Models\Cotiz\ResumenColumna;
use App\Models\Cotiz\ResumenFila;
use App\Models\Cotiz\Tarjeta;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->seed(RolesSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-cotiz');
    $this->actingAs($this->user);
});

describe('resumen — index', function () {
    test('sincroniza una columna por tarjeta y renderiza la matriz', function () {
        $obra = Obra::factory()->create();
        Tarjeta::factory()->count(2)->create(['obra_id' => $obra->id]);

        $this->get(route('admin.cotiz.resumen.index', $obra))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cotiz/resumen/index')
                ->has('filas')
                ->has('columnas', 2)
                ->has('matriz')
                ->has('importeTotalVenta')
            );

        expect(ResumenColumna::where('obra_id', $obra->id)->count())->toBe(2);
        $this->assertDatabaseCount('cotiz_resumen_columna_tarjetas', 2);
    });

    test('borra columnas huérfanas al desaparecer su tarjeta', function () {
        $obra = Obra::factory()->create();
        $tarjeta = Tarjeta::factory()->create(['obra_id' => $obra->id]);

        $this->get(route('admin.cotiz.resumen.index', $obra))->assertOk();
        expect(ResumenColumna::where('obra_id', $obra->id)->count())->toBe(1);

        $tarjeta->delete();
        $this->get(route('admin.cotiz.resumen.index', $obra))->assertOk();
        expect(ResumenColumna::where('obra_id', $obra->id)->count())->toBe(0);
    });
});

describe('resumen — overrides', function () {
    test('override por celda: crea y borra al vaciar', function () {
        $obra = Obra::factory()->create();
        Tarjeta::factory()->create(['obra_id' => $obra->id]);
        $this->get(route('admin.cotiz.resumen.index', $obra))->assertOk();

        $fila = ResumenFila::factory()->create(['tipo_formula' => 'por_kg']);
        $columna = ResumenColumna::where('obra_id', $obra->id)->firstOrFail();

        $this->put(route('admin.cotiz.resumen.celda', $obra), ['fila_id' => $fila->id, 'columna_id' => $columna->id, 'coef' => 3.5])
            ->assertRedirect();
        $this->assertDatabaseHas('cotiz_obra_resumen_celda_override', ['obra_id' => $obra->id, 'fila_id' => $fila->id, 'coef' => 3.5]);

        $this->put(route('admin.cotiz.resumen.celda', $obra), ['fila_id' => $fila->id, 'columna_id' => $columna->id, 'coef' => null])
            ->assertRedirect();
        expect(ObraResumenCeldaOverride::where('obra_id', $obra->id)->count())->toBe(0);
    });

    test('override por fila y sueldo de columna', function () {
        $obra = Obra::factory()->create();
        Tarjeta::factory()->create(['obra_id' => $obra->id]);
        $this->get(route('admin.cotiz.resumen.index', $obra))->assertOk();
        $fila = ResumenFila::factory()->create(['tipo_formula' => 'margen']);
        $columna = ResumenColumna::where('obra_id', $obra->id)->firstOrFail();

        $this->put(route('admin.cotiz.resumen.coeficiente', $obra), ['fila_id' => $fila->id, 'coef' => 0.12])->assertRedirect();
        $this->assertDatabaseHas('cotiz_obra_resumen_coeficientes', ['obra_id' => $obra->id, 'fila_id' => $fila->id, 'coef' => 0.12]);

        $this->put(route('admin.cotiz.resumen.columna-sueldo', $columna), ['sueldo_mo_pza' => 4.91])->assertRedirect();
        $this->assertDatabaseHas('cotiz_resumen_columnas', ['id' => $columna->id, 'sueldo_mo_pza' => 4.91]);
    });
});

describe('caratula', function () {
    test('renderiza el resumen ejecutivo de la obra', function () {
        $obra = Obra::factory()->create();
        Tarjeta::factory()->create(['obra_id' => $obra->id]);

        $this->get(route('admin.cotiz.caratula.index', $obra))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cotiz/caratula/index')
                ->has('columnas')
                ->has('obraTotales')
                ->has('importeTotalVenta')
            );
    });

    test('exporta la carátula a PDF', function () {
        $obra = Obra::factory()->create();
        Tarjeta::factory()->create(['obra_id' => $obra->id]);

        $res = $this->get(route('admin.cotiz.caratula.pdf', $obra));

        $res->assertOk();
        expect($res->headers->get('content-type'))->toContain('application/pdf');
    });
});
