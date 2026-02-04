<?php

use App\Models\Intra\SeccionEstatica;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    Storage::fake('public');
});

describe('admin seccion estatica', function () {
    test('index page can be rendered', function () {
        SeccionEstatica::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.intra.secciones.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/intra/secciones/index')
            ->has('secciones.data', 3)
        );
    });

    test('index page can search secciones', function () {
        SeccionEstatica::factory()->create(['titulo' => 'ISO 9001']);
        SeccionEstatica::factory()->create(['titulo' => 'Misión y Visión']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.intra.secciones.index', ['search' => 'ISO']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('secciones.data', 1)
            ->where('filters.search', 'ISO')
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.intra.secciones.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/intra/secciones/create')
        );
    });

    test('seccion can be stored with auto-generated slug', function () {
        $file = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post(route('admin.intra.secciones.store'), [
                'titulo' => 'ISO 9001',
                'descripcion' => 'Sistema de gestión de calidad',
                'boton' => 'Ver ISO',
                'activo' => true,
                'file' => $file,
            ]);

        $response->assertRedirect(route('admin.intra.secciones.index'));

        $this->assertDatabaseHas('intra_seccion_estatica', [
            'slug' => 'iso-9001',
            'titulo' => 'ISO 9001',
        ]);

        $seccion = SeccionEstatica::where('slug', 'iso-9001')->first();
        expect($seccion->media)->not->toBeNull();
    });

    test('seccion generates unique slug when duplicate exists', function () {
        SeccionEstatica::factory()->create(['slug' => 'iso-9001', 'titulo' => 'Existing']);
        $file = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post(route('admin.intra.secciones.store'), [
                'titulo' => 'ISO 9001',
                'boton' => 'Ver ISO',
                'activo' => true,
                'file' => $file,
            ]);

        $response->assertRedirect(route('admin.intra.secciones.index'));

        $this->assertDatabaseHas('intra_seccion_estatica', [
            'slug' => 'iso-9001-1',
            'titulo' => 'ISO 9001',
        ]);
    });

    test('seccion requires file on store', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.intra.secciones.store'), [
                'titulo' => 'Misión y Visión',
                'boton' => 'Ver Misión',
                'activo' => true,
            ]);

        $response->assertSessionHasErrors(['file']);

        $this->assertDatabaseMissing('intra_seccion_estatica', [
            'slug' => 'mision-y-vision',
        ]);
    });

    test('edit page can be rendered', function () {
        $seccion = SeccionEstatica::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.intra.secciones.edit', $seccion));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/intra/secciones/edit')
            ->has('seccion')
        );
    });

    test('seccion can be updated with auto-regenerated slug', function () {
        $seccion = SeccionEstatica::factory()->create([
            'slug' => 'old-slug',
            'titulo' => 'Old Title',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.intra.secciones.update', $seccion), [
                'titulo' => 'New Title',
                'boton' => 'New Button',
                'activo' => false,
            ]);

        $response->assertRedirect(route('admin.intra.secciones.index'));

        $this->assertDatabaseHas('intra_seccion_estatica', [
            'id' => $seccion->id,
            'slug' => 'new-title',
            'titulo' => 'New Title',
            'activo' => false,
        ]);
    });

    test('seccion can be updated with new file', function () {
        $seccion = SeccionEstatica::factory()->create();

        $oldMedia = Media::factory()->create([
            'mediable_type' => SeccionEstatica::class,
            'mediable_id' => $seccion->id,
            'path' => 'intra/secciones/old.pdf',
        ]);

        $newFile = UploadedFile::fake()->create('new.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->put(route('admin.intra.secciones.update', $seccion), [
                'titulo' => $seccion->titulo,
                'boton' => $seccion->boton,
                'activo' => true,
                'file' => $newFile,
            ]);

        $response->assertRedirect(route('admin.intra.secciones.index'));

        // Old media should be deleted
        $this->assertDatabaseMissing('media', ['id' => $oldMedia->id]);

        // New media should exist
        $seccion->refresh();
        expect($seccion->media)->not->toBeNull();
        expect($seccion->media->id)->not->toBe($oldMedia->id);
    });

    test('seccion can be deleted', function () {
        $seccion = SeccionEstatica::factory()->create();
        $media = Media::factory()->create([
            'mediable_type' => SeccionEstatica::class,
            'mediable_id' => $seccion->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.intra.secciones.destroy', $seccion));

        $response->assertRedirect(route('admin.intra.secciones.index'));

        $this->assertDatabaseMissing('intra_seccion_estatica', ['id' => $seccion->id]);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    });

    test('validation requires titulo and boton', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.intra.secciones.store'), [
                'descripcion' => 'Some description',
            ]);

        $response->assertSessionHasErrors(['titulo', 'boton']);
    });
});

describe('guest access', function () {
    test('guests cannot access admin secciones index', function () {
        $response = $this->get(route('admin.intra.secciones.index'));

        $response->assertRedirect(route('login'));
    });

    test('guests cannot create secciones', function () {
        $response = $this->post(route('admin.intra.secciones.store'), []);

        $response->assertRedirect(route('login'));
    });
});
