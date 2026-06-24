<?php

use App\Models\Cob\Estimacion;
use App\Models\Cob\Partida;
use App\Models\Obra;
use App\Models\Proyecto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->proyecto = Proyecto::factory()->create();
    $this->obra = Obra::factory()->create(['proyecto_id' => $this->proyecto->id, 'tipo' => 'base']);
});

describe('admin cob estimaciones (multi-nivel)', function () {
    test('create page can be rendered', function () {
        $this->actingAs($this->user)
            ->get(route('admin.cob.proyectos.estimaciones.create', $this->proyecto))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cob/estimaciones/create')
                ->has('proyecto')
                ->has('obras')
                ->has('nextNumber')
            );
    });

    test('estimacion global (nivel proyecto) se guarda sin obra', function () {
        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.estimaciones.store', $this->proyecto), [
                'nivel' => 'proyecto',
                'numero_estimacion' => 1,
                'monto_estimado' => 500000,
                'moneda' => 'MXN',
            ])
            ->assertRedirect(route('admin.cob.proyectos.show', $this->proyecto));

        $this->assertDatabaseHas('cob_estimaciones', [
            'proyecto_id' => $this->proyecto->id,
            'obra_id' => null,
            'nivel' => 'proyecto',
            'numero_estimacion' => 1,
        ]);
    });

    test('estimacion de obra se guarda con obra_id', function () {
        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.estimaciones.store', $this->proyecto), [
                'nivel' => 'obra',
                'obra_id' => $this->obra->id,
                'numero_estimacion' => 1,
                'monto_estimado' => 100000,
                'moneda' => 'MXN',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cob_estimaciones', [
            'proyecto_id' => $this->proyecto->id,
            'obra_id' => $this->obra->id,
            'nivel' => 'obra',
        ]);
    });

    test('estimacion de partidas sincroniza el pivote', function () {
        $p1 = Partida::factory()->create(['obra_id' => $this->obra->id]);
        $p2 = Partida::factory()->create(['obra_id' => $this->obra->id]);

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.estimaciones.store', $this->proyecto), [
                'nivel' => 'partida',
                'obra_id' => $this->obra->id,
                'partida_ids' => [$p1->id, $p2->id],
                'numero_estimacion' => 1,
                'monto_estimado' => 80000,
                'moneda' => 'MXN',
            ])
            ->assertRedirect();

        $estimacion = Estimacion::firstWhere('nivel', 'partida');
        expect($estimacion->partidas()->count())->toBe(2);
    });

    test('rechaza obra que no pertenece al proyecto', function () {
        $ajena = Obra::factory()->create(['tipo' => 'base']); // otro proyecto

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.estimaciones.store', $this->proyecto), [
                'nivel' => 'obra',
                'obra_id' => $ajena->id,
                'numero_estimacion' => 1,
                'monto_estimado' => 1000,
                'moneda' => 'MXN',
            ])
            ->assertSessionHasErrors('obra_id');
    });

    test('rechaza partidas que no pertenecen a la obra', function () {
        $otraObra = Obra::factory()->create(['proyecto_id' => $this->proyecto->id, 'tipo' => 'adicional']);
        $partidaAjena = Partida::factory()->create(['obra_id' => $otraObra->id]);

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.estimaciones.store', $this->proyecto), [
                'nivel' => 'partida',
                'obra_id' => $this->obra->id,
                'partida_ids' => [$partidaAjena->id],
                'numero_estimacion' => 1,
                'monto_estimado' => 1000,
                'moneda' => 'MXN',
            ])
            ->assertSessionHasErrors('partida_ids');
    });

    test('numero_estimacion es secuencial por proyecto', function () {
        Estimacion::factory()->create(['proyecto_id' => $this->proyecto->id, 'obra_id' => $this->obra->id, 'numero_estimacion' => 3]);

        $this->actingAs($this->user)
            ->get(route('admin.cob.proyectos.estimaciones.create', $this->proyecto))
            ->assertInertia(fn ($page) => $page->where('nextNumber', 4));
    });

    test('el servidor asigna el consecutivo del proyecto entre sus obras e ignora el valor del cliente', function () {
        $otraObra = Obra::factory()->create(['proyecto_id' => $this->proyecto->id, 'tipo' => 'adicional']);
        // Ya existe la #3 en la obra base del proyecto.
        Estimacion::factory()->create(['proyecto_id' => $this->proyecto->id, 'obra_id' => $this->obra->id, 'numero_estimacion' => 3]);

        // Se crea en OTRA obra del mismo proyecto enviando un número falso: debe asignar 4.
        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.estimaciones.store', $this->proyecto), [
                'nivel' => 'obra',
                'obra_id' => $otraObra->id,
                'numero_estimacion' => 999,
                'monto_estimado' => 10000,
                'moneda' => 'MXN',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cob_estimaciones', [
            'proyecto_id' => $this->proyecto->id,
            'obra_id' => $otraObra->id,
            'numero_estimacion' => 4,
        ]);
        $this->assertDatabaseMissing('cob_estimaciones', ['numero_estimacion' => 999]);
    });

    test('edit page can be rendered', function () {
        $estimacion = Estimacion::factory()->create(['proyecto_id' => $this->proyecto->id, 'obra_id' => $this->obra->id]);

        $this->actingAs($this->user)
            ->get(route('admin.cob.proyectos.estimaciones.edit', [$this->proyecto, $estimacion]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cob/estimaciones/edit')
                ->has('proyecto')
                ->has('obras')
                ->has('estimacion')
                ->has('partidaIds')
            );
    });

    test('estimacion can be updated', function () {
        $estimacion = Estimacion::factory()->create(['proyecto_id' => $this->proyecto->id, 'obra_id' => $this->obra->id]);

        $this->actingAs($this->user)
            ->put(route('admin.cob.proyectos.estimaciones.update', [$this->proyecto, $estimacion]), [
                'nivel' => 'proyecto',
                'folio' => 'EST-UPD',
                'monto_estimado' => 600000,
                'moneda' => 'MXN',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cob_estimaciones', ['id' => $estimacion->id, 'folio' => 'EST-UPD', 'nivel' => 'proyecto', 'obra_id' => null]);
    });

    test('estimacion can be deleted', function () {
        $estimacion = Estimacion::factory()->create(['proyecto_id' => $this->proyecto->id, 'obra_id' => $this->obra->id]);

        $this->actingAs($this->user)
            ->delete(route('admin.cob.proyectos.estimaciones.destroy', [$this->proyecto, $estimacion]))
            ->assertRedirect(route('admin.cob.proyectos.show', $this->proyecto));

        $this->assertDatabaseMissing('cob_estimaciones', ['id' => $estimacion->id]);
    });

    test('estado can be changed from ingresada to autorizada', function () {
        $estimacion = Estimacion::factory()->create(['proyecto_id' => $this->proyecto->id, 'obra_id' => $this->obra->id, 'estado' => 'ingresada']);

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.estimaciones.cambiar-estado', [$this->proyecto, $estimacion]), [
                'estado' => 'autorizada',
                'folio' => 'FOL-001',
                'comentario' => 'Se autoriza',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cob_estimaciones', ['id' => $estimacion->id, 'estado' => 'autorizada']);
    });

    test('invalid estado transition is rejected', function () {
        $estimacion = Estimacion::factory()->create(['proyecto_id' => $this->proyecto->id, 'obra_id' => $this->obra->id, 'estado' => 'ingresada']);

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.estimaciones.cambiar-estado', [$this->proyecto, $estimacion]), [
                'estado' => 'facturada',
            ])
            ->assertSessionHasErrors(['estado']);
    });

    test('una estimación nace en estado ingresada', function () {
        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.estimaciones.store', $this->proyecto), [
                'nivel' => 'obra',
                'obra_id' => $this->obra->id,
                'monto_estimado' => 10000,
                'moneda' => 'MXN',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cob_estimaciones', [
            'obra_id' => $this->obra->id,
            'estado' => 'ingresada',
        ]);
    });
});
