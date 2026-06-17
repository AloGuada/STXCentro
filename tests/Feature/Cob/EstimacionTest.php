<?php

use App\Models\Cob\Estimacion;
use App\Models\Obra;
use App\Models\Proyecto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->proyecto = Proyecto::factory()->create();
    $this->obraBase = Obra::factory()->create(['proyecto_id' => $this->proyecto->id, 'tipo' => 'base']);
});

describe('admin cob estimaciones (proyecto)', function () {
    test('create page can be rendered', function () {
        $this->actingAs($this->user)
            ->get(route('admin.cob.proyectos.estimaciones.create', $this->proyecto))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cob/estimaciones/create')
                ->has('proyecto')
                ->has('nextNumber')
            );
    });

    test('estimacion can be stored at proyecto level', function () {
        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.estimaciones.store', $this->proyecto), [
                'numero_estimacion' => 1,
                'folio' => 'EST-0001',
                'tipo' => 'normal',
                'fecha_emision' => '2026-01-15',
                'inicio' => '2026-01-01',
                'fin' => '2026-01-31',
                'monto_estimado' => 500000.00,
                'monto_total' => 580000.00,
                'moneda' => 'MXN',
                'comentarios' => 'Primera estimacion',
            ])
            ->assertRedirect(route('admin.cob.proyectos.show', $this->proyecto));

        $this->assertDatabaseHas('cob_estimaciones', [
            'proyecto_id' => $this->proyecto->id,
            'obra_id' => $this->obraBase->id, // obra base de transición
            'numero_estimacion' => 1,
            'folio' => 'EST-0001',
        ]);
    });

    test('numero_estimacion es secuencial por proyecto', function () {
        Estimacion::factory()->create(['proyecto_id' => $this->proyecto->id, 'numero_estimacion' => 3]);

        $this->actingAs($this->user)
            ->get(route('admin.cob.proyectos.estimaciones.create', $this->proyecto))
            ->assertInertia(fn ($page) => $page->where('nextNumber', 4));
    });

    test('edit page can be rendered', function () {
        $estimacion = Estimacion::factory()->create(['proyecto_id' => $this->proyecto->id]);

        $this->actingAs($this->user)
            ->get(route('admin.cob.proyectos.estimaciones.edit', [$this->proyecto, $estimacion]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cob/estimaciones/edit')
                ->has('proyecto')
                ->has('estimacion')
                ->has('tiposRetencion')
            );
    });

    test('estimacion can be updated', function () {
        $estimacion = Estimacion::factory()->create(['proyecto_id' => $this->proyecto->id]);

        $this->actingAs($this->user)
            ->put(route('admin.cob.proyectos.estimaciones.update', [$this->proyecto, $estimacion]), [
                'folio' => 'EST-UPDATED',
                'tipo' => 'extraordinaria',
                'fecha_emision' => '2026-02-01',
                'inicio' => '2026-02-01',
                'fin' => '2026-02-28',
                'monto_estimado' => 600000.00,
                'monto_total' => 696000.00,
                'moneda' => 'MXN',
                'comentarios' => 'Actualizada',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cob_estimaciones', [
            'id' => $estimacion->id,
            'folio' => 'EST-UPDATED',
            'tipo' => 'extraordinaria',
        ]);
    });

    test('estimacion can be deleted', function () {
        $estimacion = Estimacion::factory()->create(['proyecto_id' => $this->proyecto->id]);

        $this->actingAs($this->user)
            ->delete(route('admin.cob.proyectos.estimaciones.destroy', [$this->proyecto, $estimacion]))
            ->assertRedirect(route('admin.cob.proyectos.show', $this->proyecto));

        $this->assertDatabaseMissing('cob_estimaciones', ['id' => $estimacion->id]);
    });

    test('estimacion estado can be changed from pendiente to generada', function () {
        $estimacion = Estimacion::factory()->create(['proyecto_id' => $this->proyecto->id, 'estado' => 'pendiente']);

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.estimaciones.cambiar-estado', [$this->proyecto, $estimacion]), [
                'estado' => 'generada',
                'folio' => 'FOL-001',
                'comentario' => 'Se genera la estimacion',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cob_estimaciones', ['id' => $estimacion->id, 'estado' => 'generada']);
    });

    test('invalid estado transition is rejected', function () {
        $estimacion = Estimacion::factory()->create(['proyecto_id' => $this->proyecto->id, 'estado' => 'pendiente']);

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.estimaciones.cambiar-estado', [$this->proyecto, $estimacion]), [
                'estado' => 'facturada',
                'folio' => null,
                'comentario' => null,
            ])
            ->assertSessionHasErrors(['estado']);

        $this->assertDatabaseHas('cob_estimaciones', ['id' => $estimacion->id, 'estado' => 'pendiente']);
    });
});
