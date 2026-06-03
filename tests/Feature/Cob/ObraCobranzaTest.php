<?php

use App\Models\Cliente;
use App\Models\Cob\Anticipo;
use App\Models\Cob\Estimacion;
use App\Models\Cob\Partida;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin cob obras', function () {
    test('index page can be rendered', function () {
        Obra::factory()->count(3)->create(['estatus' => 'abierta']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.cob.obras.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/cob/obras/index')
            ->has('obras', 3)
        );
    });

    test('index oculta obras cerradas por defecto', function () {
        Obra::factory()->count(2)->create(['estatus' => 'abierta']);
        Obra::factory()->count(3)->create(['estatus' => 'cerrada']);

        $this->actingAs($this->user)
            ->get(route('admin.cob.obras.index'))
            ->assertInertia(fn ($page) => $page
                ->has('obras', 2)
                ->where('filters.estatus', 'abierta')
            );
    });

    test('index muestra solo cerradas con filtro estatus=cerrada', function () {
        Obra::factory()->count(2)->create(['estatus' => 'abierta']);
        Obra::factory()->count(3)->create(['estatus' => 'cerrada']);

        $this->actingAs($this->user)
            ->get(route('admin.cob.obras.index', ['estatus' => 'cerrada']))
            ->assertInertia(fn ($page) => $page->has('obras', 3));
    });

    test('index muestra todas con filtro estatus=todas', function () {
        Obra::factory()->count(2)->create(['estatus' => 'abierta']);
        Obra::factory()->count(3)->create(['estatus' => 'cerrada']);

        $this->actingAs($this->user)
            ->get(route('admin.cob.obras.index', ['estatus' => 'todas']))
            ->assertInertia(fn ($page) => $page->has('obras', 5));
    });

    test('show page renders with all related data', function () {
        $cliente = Cliente::factory()->create();
        $obra = Obra::factory()->create(['cliente_id' => $cliente->id]);
        Partida::factory()->create(['obra_id' => $obra->id]);
        Estimacion::factory()->create(['obra_id' => $obra->id]);
        Anticipo::factory()->create(['obra_id' => $obra->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.cob.obras.show', $obra));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/cob/obras/show')
            ->has('obra')
            ->has('clientes')
        );
    });

    test('financial fields can be updated', function () {
        $cliente = Cliente::factory()->create();
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.cob.obras.update-financial', $obra), [
                'cliente_id' => $cliente->id,
                'tipo_contrato' => 'Llave en mano',
                'monto' => 5000000,
                'monto_iva' => 800000,
                'anticipo' => 500000,
                'garantia' => 250000,
                'peso' => 1200.50,
                'porcentaje_fabricacion' => 60,
                'porcentaje_montaje' => 30,
                'porcentaje_otros' => 10,
                'descripcion_otros' => 'Ingenieria',
                'porcentaje_obra' => 45.5,
                'activa' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('obras', [
            'id' => $obra->id,
            'cliente_id' => $cliente->id,
            'tipo_contrato' => 'Llave en mano',
            'porcentaje_obra' => 45.5,
        ]);
    });
});
