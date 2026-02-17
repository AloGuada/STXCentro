<?php

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Corte;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Liquidacion;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Registro;
use App\Models\Prod\TipoPagoExtra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin cortes', function () {
    test('index page can be rendered', function () {
        Corte::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.cortes.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/cortes/index')
            ->has('cortes.data', 3)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.cortes.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/cortes/create')
        );
    });

    test('corte can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.cortes.store'), [
                'semana' => 6,
                'fecha_inicio' => '2026-02-03',
                'fecha_fin' => '2026-02-09',
            ]);

        $corte = Corte::first();
        $response->assertRedirect(route('admin.prod.cortes.show', $corte));

        $this->assertDatabaseHas('prod_cortes', [
            'semana' => 6,
            'cerrado' => false,
        ]);
        expect($corte->fecha_inicio->format('Y-m-d'))->toBe('2026-02-03');
        expect($corte->fecha_fin->format('Y-m-d'))->toBe('2026-02-09');
    });

    test('show page renders with liquidaciones', function () {
        $corte = Corte::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.cortes.show', $corte));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/cortes/show')
            ->has('corte')
        );
    });

    test('corte can be deleted if not cerrado', function () {
        $corte = Corte::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.cortes.destroy', $corte));

        $response->assertRedirect(route('admin.prod.cortes.index'));
        $this->assertDatabaseMissing('prod_cortes', ['id' => $corte->id]);
    });

    test('corte cannot be deleted if cerrado', function () {
        $corte = Corte::factory()->cerrado()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.cortes.destroy', $corte));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('prod_cortes', ['id' => $corte->id]);
    });

    test('cerrar generates liquidaciones correctly', function () {
        $obra = Obra::factory()->create();
        $concepto = Concepto::factory()->create([
            'obra_id' => $obra->id,
            'peso_unitario' => 10.000,
        ]);
        $gp = GrupoPrecio::factory()->create([
            'obra_id' => $obra->id,
            'precio_kilo' => 5.0000,
        ]);
        GrupoPrecioConcepto::create([
            'concepto_id' => $concepto->id,
            'grupo_precio_id' => $gp->id,
        ]);

        $grupo = GrupoTrabajo::factory()->create();
        GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => $grupo->id,
            'nombre' => 'Juan',
            'porcentaje' => 60,
        ]);
        GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => $grupo->id,
            'nombre' => 'Pedro',
            'porcentaje' => 40,
        ]);

        $corte = Corte::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        Registro::factory()->create([
            'fecha' => '2026-02-05',
            'concepto_id' => $concepto->id,
            'grupo_trabajo_id' => $grupo->id,
            'cantidad' => 20,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.cortes.cerrar', $corte));

        $response->assertRedirect();

        $corte->refresh();
        expect($corte->cerrado)->toBeTrue();
        expect($corte->fecha_cierre)->not->toBeNull();

        // 20 piezas * 10 kg/u = 200 kg, 200 kg * 5 $/kg = 1000
        $liquidacion = Liquidacion::where('corte_id', $corte->id)->first();
        expect($liquidacion)->not->toBeNull();
        expect((float) $liquidacion->total_kilos)->toBe(200.0);
        expect((float) $liquidacion->total_produccion)->toBe(1000.0);
        expect((float) $liquidacion->total_final)->toBe(1000.0);

        // Check detalles
        expect($liquidacion->detalles)->toHaveCount(1);
        $detalle = $liquidacion->detalles->first();
        expect($detalle->cantidad)->toBe(20);

        // Check empleados snapshot
        expect($liquidacion->empleados)->toHaveCount(2);
        $juan = $liquidacion->empleados->firstWhere('nombre', 'Juan');
        expect((float) $juan->porcentaje)->toBe(60.0);
        expect((float) $juan->monto_asignado)->toBe(600.0);

        $pedro = $liquidacion->empleados->firstWhere('nombre', 'Pedro');
        expect((float) $pedro->monto_asignado)->toBe(400.0);
    });

    test('cerrar cannot be called twice', function () {
        $corte = Corte::factory()->cerrado()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.cortes.cerrar', $corte));

        $response->assertSessionHasErrors(['error']);
    });

    test('cerrar includes pagos extra in totals', function () {
        $obra = Obra::factory()->create();
        $concepto = Concepto::factory()->create([
            'obra_id' => $obra->id,
            'peso_unitario' => 10.000,
        ]);
        $gp = GrupoPrecio::factory()->create([
            'obra_id' => $obra->id,
            'precio_kilo' => 5.0000,
        ]);
        GrupoPrecioConcepto::create([
            'concepto_id' => $concepto->id,
            'grupo_precio_id' => $gp->id,
        ]);

        $grupo = GrupoTrabajo::factory()->create();
        $corte = Corte::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        Registro::factory()->create([
            'fecha' => '2026-02-05',
            'concepto_id' => $concepto->id,
            'grupo_trabajo_id' => $grupo->id,
            'cantidad' => 20,
        ]);

        $tipo = TipoPagoExtra::create([
            'descripcion' => 'Bono',
            'orden' => 1,
            'desgloce' => false,
        ]);

        PagoExtra::create([
            'descripcion' => 'Bono semanal',
            'tipo_id' => $tipo->id,
            'corte_id' => $corte->id,
            'grupo_trabajo_id' => $grupo->id,
            'precio' => 100.00,
            'dias' => 2,
            'personas' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.cortes.cerrar', $corte));

        $response->assertRedirect();

        // 20 piezas * 10 kg/u = 200 kg * 5 $/kg = 1000 produccion
        // extras: 100 * 2 * 1 = 200
        $liquidacion = Liquidacion::where('corte_id', $corte->id)->first();
        expect($liquidacion)->not->toBeNull();
        expect((float) $liquidacion->total_produccion)->toBe(1000.0);
        expect((float) $liquidacion->total_extras)->toBe(200.0);
        expect((float) $liquidacion->total_final)->toBe(1200.0);
    });

    test('show page includes preview for open corte', function () {
        $corte = Corte::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.cortes.show', $corte));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/cortes/show')
            ->has('registrosPreview')
            ->has('pagosExtraPreview')
        );
    });
});
