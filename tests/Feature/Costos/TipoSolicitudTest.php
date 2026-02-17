<?php

use App\Models\Costos\TipoSolicitud;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin costos tipo solicitudes', function () {
    test('index page can be rendered', function () {
        TipoSolicitud::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.tipo-solicitudes.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/tipo-solicitudes/index')
            ->has('tipoSolicitudes.data', 3)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.tipo-solicitudes.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/tipo-solicitudes/create')
        );
    });

    test('tipo solicitud can be stored with documentos', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.tipo-solicitudes.store'), [
                'titulo' => 'Solicitud de Compra',
                'descripcion' => 'Para compras generales',
                'rubros' => true,
                'documentos' => [
                    [
                        'titulo' => 'Factura',
                        'multiple' => false,
                        'texto' => 'Adjuntar factura',
                        'texto_adicional' => '',
                    ],
                    [
                        'titulo' => 'Cotizaciones',
                        'multiple' => true,
                        'texto' => 'Mínimo 3 cotizaciones',
                        'texto_adicional' => '',
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.costos.tipo-solicitudes.index'));
        $this->assertDatabaseHas('costos_tipo_solicitud', [
            'titulo' => 'Solicitud de Compra',
        ]);
        $this->assertDatabaseCount('costos_documentos', 2);
    });

    test('edit page can be rendered with documentos', function () {
        $tipoSolicitud = TipoSolicitud::factory()->create();
        $tipoSolicitud->documentos()->create([
            'titulo' => 'Doc Test',
            'multiple' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.tipo-solicitudes.edit', $tipoSolicitud));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/tipo-solicitudes/edit')
            ->has('tipoSolicitud')
            ->has('tipoSolicitud.documentos', 1)
        );
    });

    test('tipo solicitud can be updated with documentos sync', function () {
        $tipoSolicitud = TipoSolicitud::factory()->create();
        $existingDoc = $tipoSolicitud->documentos()->create([
            'titulo' => 'Existing Doc',
            'multiple' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.costos.tipo-solicitudes.update', $tipoSolicitud), [
                'titulo' => 'Updated Title',
                'rubros' => false,
                'documentos' => [
                    [
                        'id' => $existingDoc->id,
                        'titulo' => 'Updated Doc',
                        'multiple' => true,
                        'texto' => '',
                        'texto_adicional' => '',
                    ],
                    [
                        'titulo' => 'New Doc',
                        'multiple' => false,
                        'texto' => '',
                        'texto_adicional' => '',
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.costos.tipo-solicitudes.index'));
        $this->assertDatabaseHas('costos_tipo_solicitud', [
            'id' => $tipoSolicitud->id,
            'titulo' => 'Updated Title',
        ]);
        $this->assertDatabaseHas('costos_documentos', [
            'id' => $existingDoc->id,
            'titulo' => 'Updated Doc',
        ]);
        $this->assertDatabaseCount('costos_documentos', 2);
    });

    test('tipo solicitud can be deleted', function () {
        $tipoSolicitud = TipoSolicitud::factory()->create();
        $tipoSolicitud->documentos()->create(['titulo' => 'Doc', 'multiple' => false]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.costos.tipo-solicitudes.destroy', $tipoSolicitud));

        $response->assertRedirect(route('admin.costos.tipo-solicitudes.index'));
        $this->assertDatabaseMissing('costos_tipo_solicitud', ['id' => $tipoSolicitud->id]);
        $this->assertDatabaseCount('costos_documentos', 0);
    });

    test('validation requires titulo', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.tipo-solicitudes.store'), []);

        $response->assertSessionHasErrors(['titulo']);
    });
});
