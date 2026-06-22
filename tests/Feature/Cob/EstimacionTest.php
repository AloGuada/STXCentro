<?php

use App\Models\Cob\Estimacion;
use App\Models\Obra;
use App\Models\Proyecto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->proyecto = Proyecto::factory()->create();
    $this->obra = Obra::factory()->create(['proyecto_id' => $this->proyecto->id, 'tipo' => 'base']);
});

describe('admin cob estimaciones (obra)', function () {
    test('create page can be rendered', function () {
        $this->actingAs($this->user)
            ->get(route('admin.cob.obras.estimaciones.create', $this->obra))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cob/estimaciones/create')
                ->has('obra')
                ->has('nextNumber')
            );
    });

    test('estimacion can be stored at obra level', function () {
        $this->actingAs($this->user)
            ->post(route('admin.cob.obras.estimaciones.store', $this->obra), [
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
            ->assertRedirect(route('admin.cob.obras.show', $this->obra));

        $this->assertDatabaseHas('cob_estimaciones', [
            'obra_id' => $this->obra->id,
            'numero_estimacion' => 1,
            'folio' => 'EST-0001',
        ]);
    });

    test('numero_estimacion es secuencial por obra', function () {
        Estimacion::factory()->create(['obra_id' => $this->obra->id, 'numero_estimacion' => 3]);

        $this->actingAs($this->user)
            ->get(route('admin.cob.obras.estimaciones.create', $this->obra))
            ->assertInertia(fn ($page) => $page->where('nextNumber', 4));
    });

    test('edit page can be rendered', function () {
        $estimacion = Estimacion::factory()->create(['obra_id' => $this->obra->id]);

        $this->actingAs($this->user)
            ->get(route('admin.cob.obras.estimaciones.edit', [$this->obra, $estimacion]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cob/estimaciones/edit')
                ->has('obra')
                ->has('estimacion')
                ->has('tiposRetencion')
            );
    });

    test('estimacion can be updated', function () {
        $estimacion = Estimacion::factory()->create(['obra_id' => $this->obra->id]);

        $this->actingAs($this->user)
            ->put(route('admin.cob.obras.estimaciones.update', [$this->obra, $estimacion]), [
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
        $estimacion = Estimacion::factory()->create(['obra_id' => $this->obra->id]);

        $this->actingAs($this->user)
            ->delete(route('admin.cob.obras.estimaciones.destroy', [$this->obra, $estimacion]))
            ->assertRedirect(route('admin.cob.obras.show', $this->obra));

        $this->assertDatabaseMissing('cob_estimaciones', ['id' => $estimacion->id]);
    });

    test('estimacion estado can be changed from pendiente to generada', function () {
        $estimacion = Estimacion::factory()->create(['obra_id' => $this->obra->id, 'estado' => 'pendiente']);

        $this->actingAs($this->user)
            ->post(route('admin.cob.obras.estimaciones.cambiar-estado', [$this->obra, $estimacion]), [
                'estado' => 'generada',
                'folio' => 'FOL-001',
                'comentario' => 'Se genera la estimacion',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cob_estimaciones', ['id' => $estimacion->id, 'estado' => 'generada']);
    });

    test('invalid estado transition is rejected', function () {
        $estimacion = Estimacion::factory()->create(['obra_id' => $this->obra->id, 'estado' => 'pendiente']);

        $this->actingAs($this->user)
            ->post(route('admin.cob.obras.estimaciones.cambiar-estado', [$this->obra, $estimacion]), [
                'estado' => 'facturada',
                'folio' => null,
                'comentario' => null,
            ])
            ->assertSessionHasErrors(['estado']);

        $this->assertDatabaseHas('cob_estimaciones', ['id' => $estimacion->id, 'estado' => 'pendiente']);
    });
});
