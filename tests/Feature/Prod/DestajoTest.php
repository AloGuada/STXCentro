<?php

use App\Models\Pieza;
use App\Models\Prod\Destajo;
use App\Models\Prod\Fabricado;
use App\Models\Prod\Grupo;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Tipo;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin destajos', function () {
    test('index page can be rendered', function () {
        Destajo::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/destajos/index')
            ->has('destajos.data', 3)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/destajos/create')
        );
    });

    test('destajo can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.store'), [
                'semana' => 5,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('prod_destajos', [
            'semana' => 5,
            'cerrada' => 0,
        ]);
    });

    test('show page can be rendered', function () {
        $destajo = Destajo::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.show', $destajo));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/destajos/show')
            ->has('destajo')
            ->has('grupos')
            ->has('piezas')
            ->has('tipos')
        );
    });

    test('destajo can be cerrado', function () {
        $destajo = Destajo::factory()->create(['cerrada' => false]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.cerrar', $destajo));

        $response->assertRedirect();

        $destajo->refresh();
        expect($destajo->cerrada)->toBeTrue();
        expect($destajo->fecha_cierre)->not->toBeNull();
    });

    test('cerrar calculates cantidad total', function () {
        $destajo = Destajo::factory()->create(['cerrada' => false]);
        $pieza = Pieza::factory()->create(['peso' => 100]);
        $grupo = Grupo::factory()->create();

        Fabricado::create([
            'destajo_id' => $destajo->id,
            'dest_grupo_id' => $grupo->id,
            'pieza_id' => $pieza->id,
            'cantidad' => 2,
            'porcentual' => 100,
            'precio_unitario_aplicado' => 10.00,
            'total_calculado' => 2000.00,
            'saldo_pendiente' => 0,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.cerrar', $destajo));

        $destajo->refresh();
        expect((float) $destajo->cantidad)->toBe(2000.00);
    });

    test('cerrar auto-arrastra saldos pendientes', function () {
        $destajo = Destajo::factory()->create(['semana' => 5, 'cerrada' => false]);
        $pieza = Pieza::factory()->create(['peso' => 50]);
        $grupo = Grupo::factory()->create();

        Fabricado::create([
            'destajo_id' => $destajo->id,
            'dest_grupo_id' => $grupo->id,
            'pieza_id' => $pieza->id,
            'cantidad' => 1,
            'porcentual' => 60,
            'precio_unitario_aplicado' => 10.00,
            'total_calculado' => 300.00,
            'saldo_pendiente' => 300.00,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.cerrar', $destajo));

        // Debe haberse creado un nuevo destajo con semana+1
        $nuevoDestajo = Destajo::where('semana', 6)
            ->where('cerrada', false)
            ->first();

        expect($nuevoDestajo)->not->toBeNull();
        expect($nuevoDestajo->fabricados)->toHaveCount(1);
        expect((float) $nuevoDestajo->fabricados->first()->porcentual)->toBe(40.00);
        expect($nuevoDestajo->fabricados->first()->pieza_id)->toBe($pieza->id);
        expect($nuevoDestajo->fabricados->first()->dest_grupo_id)->toBe($grupo->id);
    });

    test('cerrada destajo cannot be cerrado again', function () {
        $destajo = Destajo::factory()->cerrada()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.cerrar', $destajo));

        $response->assertSessionHasErrors(['error']);
    });

    test('destajo can be deleted when open', function () {
        $destajo = Destajo::factory()->create(['cerrada' => false]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.destajos.destroy', $destajo));

        $response->assertRedirect(route('admin.prod.destajos.index'));
        $this->assertDatabaseMissing('prod_destajos', ['id' => $destajo->id]);
    });

    test('cerrada destajo cannot be deleted', function () {
        $destajo = Destajo::factory()->cerrada()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.destajos.destroy', $destajo));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('prod_destajos', ['id' => $destajo->id]);
    });

    test('fabricado can be added to open destajo', function () {
        $destajo = Destajo::factory()->create(['cerrada' => false]);
        $pieza = Pieza::factory()->create(['peso' => 100]);
        $grupo = Grupo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.fabricados.store', $destajo), [
                'dest_grupo_id' => $grupo->id,
                'pieza_id' => $pieza->id,
                'cantidad' => 2,
                'porcentual' => 100,
                'precio_unitario_aplicado' => 15.00,
            ]);

        $response->assertRedirect();

        $fabricado = $destajo->fabricados()->first();
        expect($fabricado)->not->toBeNull();
        // total = 2 * 100 * (100/100) * 15 = 3000
        expect((float) $fabricado->total_calculado)->toBe(3000.00);
        expect((float) $fabricado->saldo_pendiente)->toBe(0.00);
    });

    test('fabricado with partial porcentual has saldo pendiente', function () {
        $destajo = Destajo::factory()->create(['cerrada' => false]);
        $pieza = Pieza::factory()->create(['peso' => 50]);
        $grupo = Grupo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.fabricados.store', $destajo), [
                'dest_grupo_id' => $grupo->id,
                'pieza_id' => $pieza->id,
                'cantidad' => 1,
                'porcentual' => 60,
                'precio_unitario_aplicado' => 10.00,
            ]);

        $response->assertRedirect();

        $fabricado = $destajo->fabricados()->first();
        // total = 1 * 50 * (60/100) * 10 = 300
        expect((float) $fabricado->total_calculado)->toBe(300.00);
        expect((float) $fabricado->saldo_pendiente)->toBe(300.00);
    });

    test('fabricado cannot be added to cerrada destajo', function () {
        $destajo = Destajo::factory()->cerrada()->create();
        $pieza = Pieza::factory()->create();
        $grupo = Grupo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.fabricados.store', $destajo), [
                'dest_grupo_id' => $grupo->id,
                'pieza_id' => $pieza->id,
                'cantidad' => 1,
                'porcentual' => 100,
                'precio_unitario_aplicado' => 10,
            ]);

        $response->assertSessionHasErrors(['error']);
    });

    test('fabricado can be deleted from open destajo', function () {
        $destajo = Destajo::factory()->create(['cerrada' => false]);
        $fabricado = Fabricado::factory()->create(['destajo_id' => $destajo->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.destajos.fabricados.destroy', [$destajo, $fabricado]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('prod_fabricados', ['id' => $fabricado->id]);
    });

    test('pago extra can be added to open destajo', function () {
        $destajo = Destajo::factory()->create(['cerrada' => false]);
        $tipo = Tipo::factory()->create();
        $grupo = Grupo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.pagos-extra.store', $destajo), [
                'dest_grupo_id' => $grupo->id,
                'tipo_id' => $tipo->id,
                'descripcion' => 'Bono test',
                'precio' => 200,
                'dias' => 3,
                'personas' => 2,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('prod_pagos_extra', [
            'destajo_id' => $destajo->id,
            'dest_grupo_id' => $grupo->id,
            'descripcion' => 'Bono test',
            'precio' => 200.00,
            'dias' => 3,
            'personas' => 2,
        ]);
    });

    test('pago extra can be deleted from open destajo', function () {
        $destajo = Destajo::factory()->create(['cerrada' => false]);
        $pago = PagoExtra::factory()->create(['destajo_id' => $destajo->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.destajos.pagos-extra.destroy', [$destajo, $pago]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('prod_pagos_extra', ['id' => $pago->id]);
    });

    test('pago extra cannot be added to cerrada destajo', function () {
        $destajo = Destajo::factory()->cerrada()->create();
        $tipo = Tipo::factory()->create();
        $grupo = Grupo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.pagos-extra.store', $destajo), [
                'dest_grupo_id' => $grupo->id,
                'tipo_id' => $tipo->id,
                'precio' => 500,
                'dias' => 1,
                'personas' => 1,
            ]);

        $response->assertSessionHasErrors(['error']);
    });
});
