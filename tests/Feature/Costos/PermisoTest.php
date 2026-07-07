<?php

use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\Permiso;
use App\Models\Departamento;
use App\Models\User;
use App\Models\Usuario;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin costos permisos', function () {
    test('index page can be rendered', function () {
        Permiso::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.permisos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/permisos/index')
            ->has('permisos.data', 3)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.permisos.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/permisos/create')
        );
    });

    test('permiso can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.permisos.store'), [
                'descripcion' => 'Jefe Depto',
                'nivel' => 1,
                'tipo_aprobacion' => 'solicitud_pago',
            ]);

        $response->assertRedirect(route('admin.costos.permisos.index'));
        $this->assertDatabaseHas('costos_permisos', [
            'descripcion' => 'Jefe Depto',
            'nivel' => 1,
        ]);
    });

    test('show page loads departamentos and usuarios', function () {
        $permiso = Permiso::factory()->create();
        Departamento::factory()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.permisos.show', $permiso));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/permisos/show')
            ->has('permiso')
            ->has('departamentos')
            ->has('usuarios')
            ->has('usuariosSinPermiso')
            ->has('asignaciones')
            ->has('omitir')
            ->has('rubros')
            ->has('rubrosPermitidos')
        );
    });

    test('show surfaces assigned users who lost the approval permission so they can be removed', function () {
        Permission::findOrCreate('costos.requisiciones.aprobar', 'web');

        $permiso = Permiso::factory()->create(['tipo_aprobacion' => 'requisicion']);
        $departamento = Departamento::factory()->create();

        // Candidato vigente: tiene el permiso.
        $candidato = Usuario::factory()->create();
        $candidato->givePermissionTo('costos.requisiciones.aprobar');

        // Fantasma: asignado al nivel pero ya sin el permiso de aprobar.
        $fantasma = Usuario::factory()->create();
        AprobacionDepartamento::factory()->create([
            'permiso_id' => $permiso->id,
            'departamento_id' => $departamento->id,
            'aprobador_id' => $fantasma->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.permisos.show', $permiso));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            // Ambos aparecen en la lista para poder verse/removerse.
            ->where('usuarios', fn ($usuarios) => collect($usuarios)->pluck('id')->contains($fantasma->id)
                && collect($usuarios)->pluck('id')->contains($candidato->id))
            // Solo el fantasma queda marcado como sin permiso.
            ->where('usuariosSinPermiso', fn ($ids) => collect($ids)->contains($fantasma->id)
                && ! collect($ids)->contains($candidato->id))
        );
    });

    test('edit page can be rendered', function () {
        $permiso = Permiso::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.permisos.edit', $permiso));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/permisos/edit')
            ->has('permiso')
        );
    });

    test('permiso can be updated', function () {
        $permiso = Permiso::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.costos.permisos.update', $permiso), [
                'descripcion' => 'Gerente',
                'nivel' => 2,
                'tipo_aprobacion' => 'solicitud_pago',
            ]);

        $response->assertRedirect(route('admin.costos.permisos.index'));
        $this->assertDatabaseHas('costos_permisos', [
            'id' => $permiso->id,
            'descripcion' => 'Gerente',
            'nivel' => 2,
        ]);
    });

    test('permiso can be deleted', function () {
        $permiso = Permiso::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.costos.permisos.destroy', $permiso));

        $response->assertRedirect(route('admin.costos.permisos.index'));
        $this->assertDatabaseMissing('costos_permisos', ['id' => $permiso->id]);
    });

    test('syncDepartamentos creates assignments', function () {
        $permiso = Permiso::factory()->create();
        $departamento = Departamento::factory()->create();
        $aprobador = User::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.permisos.sync-departamentos', $permiso), [
                'asignaciones' => [
                    ['departamento_id' => $departamento->id, 'aprobador_ids' => [$aprobador->id]],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('costos_aprobacion_departamento', [
            'permiso_id' => $permiso->id,
            'departamento_id' => $departamento->id,
            'aprobador_id' => $aprobador->id,
        ]);
    });

    test('syncDepartamentos replaces existing assignments', function () {
        $permiso = Permiso::factory()->create();
        $departamento = Departamento::factory()->create();
        $oldAprobador = User::factory()->create();
        $newAprobador = User::factory()->create();

        AprobacionDepartamento::factory()->create([
            'permiso_id' => $permiso->id,
            'departamento_id' => $departamento->id,
            'aprobador_id' => $oldAprobador->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.permisos.sync-departamentos', $permiso), [
                'asignaciones' => [
                    ['departamento_id' => $departamento->id, 'aprobador_ids' => [$newAprobador->id]],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('costos_aprobacion_departamento', [
            'permiso_id' => $permiso->id,
            'departamento_id' => $departamento->id,
            'aprobador_id' => $newAprobador->id,
        ]);
        $this->assertDatabaseMissing('costos_aprobacion_departamento', [
            'aprobador_id' => $oldAprobador->id,
        ]);
    });

    test('syncDepartamentos ignores empty aprobador_ids', function () {
        $permiso = Permiso::factory()->create();
        $departamento = Departamento::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.permisos.sync-departamentos', $permiso), [
                'asignaciones' => [
                    ['departamento_id' => $departamento->id, 'aprobador_ids' => []],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('costos_aprobacion_departamento', [
            'permiso_id' => $permiso->id,
            'departamento_id' => $departamento->id,
        ]);
    });

    test('syncDepartamentos persists multiple aprobadores for same departamento', function () {
        $permiso = Permiso::factory()->create();
        $departamento = Departamento::factory()->create();
        $aprobadorA = User::factory()->create();
        $aprobadorB = User::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.permisos.sync-departamentos', $permiso), [
                'asignaciones' => [
                    ['departamento_id' => $departamento->id, 'aprobador_ids' => [$aprobadorA->id, $aprobadorB->id]],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('costos_aprobacion_departamento', [
            'permiso_id' => $permiso->id,
            'departamento_id' => $departamento->id,
            'aprobador_id' => $aprobadorA->id,
        ]);
        $this->assertDatabaseHas('costos_aprobacion_departamento', [
            'permiso_id' => $permiso->id,
            'departamento_id' => $departamento->id,
            'aprobador_id' => $aprobadorB->id,
        ]);
    });

    test('syncDepartamentos guarda el flag omitir si presupuesto reservado por departamento', function () {
        $permiso = Permiso::factory()->create();
        $departamento = Departamento::factory()->create();
        $aprobador = User::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.costos.permisos.sync-departamentos', $permiso), [
                'asignaciones' => [
                    ['departamento_id' => $departamento->id, 'aprobador_ids' => [$aprobador->id], 'omitir_si_presupuesto_reservado' => true],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('costos_aprobacion_departamento', [
            'permiso_id' => $permiso->id,
            'departamento_id' => $departamento->id,
            'aprobador_id' => $aprobador->id,
            'omitir_si_presupuesto_reservado' => true,
        ]);
    });

    test('syncDepartamentos guarda los centros de costo permitidos cuando el salto está activo', function () {
        $permiso = Permiso::factory()->create();
        $departamento = Departamento::factory()->create();
        $aprobador = User::factory()->create();
        $rubro = \App\Models\Costos\Rubro::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.costos.permisos.sync-departamentos', $permiso), [
                'asignaciones' => [
                    [
                        'departamento_id' => $departamento->id,
                        'aprobador_ids' => [$aprobador->id],
                        'omitir_si_presupuesto_reservado' => true,
                        'rubro_ids' => [$rubro->id],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('costos_omitir_rubros', [
            'permiso_id' => $permiso->id,
            'departamento_id' => $departamento->id,
            'rubro_id' => $rubro->id,
        ]);
    });

    test('syncDepartamentos no guarda centros permitidos si el salto está desactivado', function () {
        $permiso = Permiso::factory()->create();
        $departamento = Departamento::factory()->create();
        $aprobador = User::factory()->create();
        $rubro = \App\Models\Costos\Rubro::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.costos.permisos.sync-departamentos', $permiso), [
                'asignaciones' => [
                    [
                        'departamento_id' => $departamento->id,
                        'aprobador_ids' => [$aprobador->id],
                        'omitir_si_presupuesto_reservado' => false,
                        'rubro_ids' => [$rubro->id],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('costos_omitir_rubros', [
            'permiso_id' => $permiso->id,
        ]);
    });

    test('validation requires descripcion and nivel', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.permisos.store'), []);

        $response->assertSessionHasErrors(['descripcion', 'nivel']);
    });
});
