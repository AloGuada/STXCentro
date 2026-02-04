<?php

use App\Enums\TipoDocumento;
use App\Models\Intra\Area;
use App\Models\Intra\Documento;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    Storage::fake('public');
});

describe('admin documento', function () {
    test('index page can be rendered', function () {
        Documento::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.intra.documentos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/intra/documentos/index')
            ->has('documentos.data', 3)
            ->has('areas')
            ->has('tipos')
        );
    });

    test('index page can search documentos', function () {
        Documento::factory()->create(['descripcion' => 'Manual de Calidad']);
        Documento::factory()->create(['descripcion' => 'Procedimiento General']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.intra.documentos.index', ['search' => 'Manual']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('documentos.data', 1)
            ->where('filters.search', 'Manual')
        );
    });

    test('index page can filter by area', function () {
        $area1 = Area::factory()->create();
        $area2 = Area::factory()->create();

        Documento::factory()->forArea($area1)->create();
        Documento::factory()->forArea($area2)->count(2)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.intra.documentos.index', ['area_id' => $area1->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('documentos.data', 1)
        );
    });

    test('index page can filter by tipo', function () {
        Documento::factory()->ofType(TipoDocumento::ManualOperativo)->create();
        Documento::factory()->ofType(TipoDocumento::Protocolo)->count(2)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.intra.documentos.index', ['tipo' => 'manual_operativo']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('documentos.data', 1)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.intra.documentos.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/intra/documentos/create')
            ->has('areas')
            ->has('tipos')
        );
    });

    test('documento can be stored', function () {
        $area = Area::factory()->create();
        $file = UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post(route('admin.intra.documentos.store'), [
                'area_id' => $area->id,
                'descripcion' => 'Manual de Calidad',
                'codigo' => 'PG-STX-MC-01',
                'tipo' => 'manual_operativo',
                'order' => 1,
                'activo' => true,
                'file' => $file,
            ]);

        $response->assertRedirect(route('admin.intra.documentos.index'));

        $this->assertDatabaseHas('intra_documentos', [
            'descripcion' => 'Manual de Calidad',
            'codigo' => 'PG-STX-MC-01',
            'area_id' => $area->id,
        ]);

        $documento = Documento::where('descripcion', 'Manual de Calidad')->first();
        expect($documento->media)->not->toBeNull();
    });

    test('edit page can be rendered', function () {
        $documento = Documento::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.intra.documentos.edit', $documento));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/intra/documentos/edit')
            ->has('documento')
            ->has('areas')
            ->has('tipos')
        );
    });

    test('documento can be updated', function () {
        $documento = Documento::factory()->create([
            'descripcion' => 'Old Description',
        ]);
        $newArea = Area::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.intra.documentos.update', $documento), [
                'area_id' => $newArea->id,
                'descripcion' => 'New Description',
                'codigo' => 'NEW-CODE',
                'tipo' => 'protocolo',
                'order' => 5,
                'activo' => false,
            ]);

        $response->assertRedirect(route('admin.intra.documentos.index'));

        $this->assertDatabaseHas('intra_documentos', [
            'id' => $documento->id,
            'descripcion' => 'New Description',
            'area_id' => $newArea->id,
            'activo' => false,
        ]);
    });

    test('documento can be updated with new file', function () {
        $documento = Documento::factory()->create();
        $oldMediaId = $documento->media_id;

        $newFile = UploadedFile::fake()->create('new.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->put(route('admin.intra.documentos.update', $documento), [
                'area_id' => $documento->area_id,
                'descripcion' => $documento->descripcion,
                'tipo' => $documento->tipo->value,
                'order' => $documento->order,
                'activo' => true,
                'file' => $newFile,
            ]);

        $response->assertRedirect(route('admin.intra.documentos.index'));

        $documento->refresh();
        expect($documento->media_id)->toBe($oldMediaId);
        Storage::disk('public')->assertExists($documento->media->path);
    });

    test('documento can be deleted', function () {
        $documento = Documento::factory()->create();
        $mediaId = $documento->media_id;

        $response = $this->actingAs($this->user)
            ->delete(route('admin.intra.documentos.destroy', $documento));

        $response->assertRedirect(route('admin.intra.documentos.index'));

        $this->assertDatabaseMissing('intra_documentos', ['id' => $documento->id]);
        $this->assertDatabaseMissing('media', ['id' => $mediaId]);
    });

    test('validation requires area_id, descripcion, tipo and file on create', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.intra.documentos.store'), [
                'order' => 1,
            ]);

        $response->assertSessionHasErrors(['area_id', 'descripcion', 'tipo', 'file']);
    });

    test('validation requires valid tipo enum', function () {
        $area = Area::factory()->create();
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post(route('admin.intra.documentos.store'), [
                'area_id' => $area->id,
                'descripcion' => 'Test',
                'tipo' => 'invalid_tipo',
                'file' => $file,
            ]);

        $response->assertSessionHasErrors(['tipo']);
    });

    test('validation requires pdf file', function () {
        $area = Area::factory()->create();
        $file = UploadedFile::fake()->create('doc.txt', 100, 'text/plain');

        $response = $this->actingAs($this->user)
            ->post(route('admin.intra.documentos.store'), [
                'area_id' => $area->id,
                'descripcion' => 'Test',
                'tipo' => 'manual_operativo',
                'file' => $file,
            ]);

        $response->assertSessionHasErrors(['file']);
    });
});

describe('guest access', function () {
    test('guests cannot access admin documentos index', function () {
        $response = $this->get(route('admin.intra.documentos.index'));

        $response->assertRedirect(route('login'));
    });

    test('guests cannot create documentos', function () {
        $response = $this->post(route('admin.intra.documentos.store'), []);

        $response->assertRedirect(route('login'));
    });
});
