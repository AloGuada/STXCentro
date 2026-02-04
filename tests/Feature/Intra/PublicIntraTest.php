<?php

use App\Enums\TipoDocumento;
use App\Models\Intra\Area;
use App\Models\Intra\Documento;
use App\Models\Intra\SeccionEstatica;
use App\Models\Media;

describe('public intra home', function () {
    test('home page can be rendered', function () {
        $response = $this->get(route('intra.home'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('intra/index')
            ->has('secciones')
            ->has('areas')
        );
    });

    test('home shows only active secciones', function () {
        SeccionEstatica::factory()->count(2)->create(['activo' => true]);
        SeccionEstatica::factory()->inactive()->create();

        $response = $this->get(route('intra.home'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('secciones', 2)
        );
    });

    test('home shows only root active areas', function () {
        $parent = Area::factory()->create(['activo' => true]);
        Area::factory()->withParent($parent)->create(['activo' => true]);
        Area::factory()->create(['activo' => true]);
        Area::factory()->inactive()->create();

        $response = $this->get(route('intra.home'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('areas', 2)
        );
    });

    test('secciones are ordered by order field', function () {
        SeccionEstatica::factory()->create(['titulo' => 'Third', 'order' => 3]);
        SeccionEstatica::factory()->create(['titulo' => 'First', 'order' => 1]);
        SeccionEstatica::factory()->create(['titulo' => 'Second', 'order' => 2]);

        $response = $this->get(route('intra.home'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('secciones.0.titulo', 'First')
            ->where('secciones.1.titulo', 'Second')
            ->where('secciones.2.titulo', 'Third')
        );
    });
});

describe('public intra seccion', function () {
    test('seccion page can be rendered', function () {
        $seccion = SeccionEstatica::factory()->create(['activo' => true]);
        Media::factory()->create([
            'mediable_type' => SeccionEstatica::class,
            'mediable_id' => $seccion->id,
        ]);

        $response = $this->get(route('intra.seccion', $seccion));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('intra/seccion/show')
            ->has('seccion')
            ->has('seccion.media')
        );
    });

    test('seccion is resolved by slug', function () {
        $seccion = SeccionEstatica::factory()->create([
            'slug' => 'iso-9001',
            'titulo' => 'ISO 9001',
            'activo' => true,
        ]);

        $response = $this->get('/intra/est/iso-9001');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('seccion.slug', 'iso-9001')
        );
    });

    test('nonexistent seccion returns 404', function () {
        $response = $this->get('/intra/est/nonexistent-slug');

        $response->assertNotFound();
    });
});

describe('public intra area', function () {
    test('area page can be rendered', function () {
        $area = Area::factory()->create(['activo' => true]);

        $response = $this->get(route('intra.area', $area));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('intra/area/show')
            ->has('area')
            ->has('documentosPorTipo')
            ->has('breadcrumbs')
        );
    });

    test('area shows child areas', function () {
        $parent = Area::factory()->create(['activo' => true]);
        Area::factory()->withParent($parent)->count(3)->create(['activo' => true]);

        $response = $this->get(route('intra.area', $parent));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('area.children', 3)
        );
    });

    test('area shows only active child areas', function () {
        $parent = Area::factory()->create(['activo' => true]);
        Area::factory()->withParent($parent)->count(2)->create(['activo' => true]);
        Area::factory()->withParent($parent)->inactive()->create();

        $response = $this->get(route('intra.area', $parent));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('area.children', 2)
        );
    });

    test('area groups documents by tipo', function () {
        $area = Area::factory()->create(['activo' => true]);

        Documento::factory()->forArea($area)->ofType(TipoDocumento::ManualOperativo)->create(['activo' => true]);
        Documento::factory()->forArea($area)->ofType(TipoDocumento::ManualOperativo)->create(['activo' => true]);
        Documento::factory()->forArea($area)->ofType(TipoDocumento::Protocolo)->create(['activo' => true]);

        $response = $this->get(route('intra.area', $area));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('documentosPorTipo.manual_operativo.documentos', 2)
            ->has('documentosPorTipo.protocolo.documentos', 1)
        );
    });

    test('area shows only active documents', function () {
        $area = Area::factory()->create(['activo' => true]);

        Documento::factory()->forArea($area)->create(['activo' => true]);
        Documento::factory()->forArea($area)->inactive()->create();

        $response = $this->get(route('intra.area', $area));

        $response->assertOk();

        $page = $response->original->getData()['page'];
        $documentosPorTipo = $page['props']['documentosPorTipo'];
        $totalDocs = collect($documentosPorTipo)->sum(fn ($grupo) => count($grupo['documentos']));

        expect($totalDocs)->toBe(1);
    });

    test('breadcrumbs include area hierarchy', function () {
        $grandparent = Area::factory()->create(['descripcion' => 'Grandparent', 'activo' => true]);
        $parent = Area::factory()->withParent($grandparent)->create(['descripcion' => 'Parent', 'activo' => true]);
        $child = Area::factory()->withParent($parent)->create(['descripcion' => 'Child', 'activo' => true]);

        $response = $this->get(route('intra.area', $child));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('breadcrumbs', 3)
            ->where('breadcrumbs.0.title', 'Grandparent')
            ->where('breadcrumbs.1.title', 'Parent')
            ->where('breadcrumbs.2.title', 'Child')
        );
    });

    test('nonexistent area returns 404', function () {
        $response = $this->get('/intra/area/99999');

        $response->assertNotFound();
    });
});

describe('public access', function () {
    test('public pages do not require authentication', function () {
        $seccion = SeccionEstatica::factory()->create(['activo' => true]);
        $area = Area::factory()->create(['activo' => true]);

        $this->get(route('intra.home'))->assertOk();
        $this->get(route('intra.seccion', $seccion))->assertOk();
        $this->get(route('intra.area', $area))->assertOk();
    });
});
