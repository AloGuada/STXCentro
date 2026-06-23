<?php

use App\Models\Cob\DocumentoArchivo;
use App\Models\Cob\DocumentoCarpeta;
use App\Models\Cob\DocumentoSeccion;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('catálogo de secciones', function () {
    test('crea una sección', function () {
        $this->actingAs($this->user)
            ->post(route('admin.cob.documento-secciones.store'), ['nombre' => 'CONTRATO', 'orden' => 0])
            ->assertRedirect();

        $this->assertDatabaseHas('cob_documento_secciones', ['nombre' => 'CONTRATO']);
    });

    test('actualiza y desactiva una sección', function () {
        $seccion = DocumentoSeccion::factory()->create(['activo' => true]);

        $this->actingAs($this->user)
            ->put(route('admin.cob.documento-secciones.update', $seccion), [
                'nombre' => $seccion->nombre,
                'orden' => $seccion->orden,
                'activo' => false,
            ])
            ->assertRedirect();

        expect($seccion->fresh()->activo)->toBeFalse();
    });

    test('no elimina una sección con carpetas', function () {
        $seccion = DocumentoSeccion::factory()->create();
        DocumentoCarpeta::factory()->create(['seccion_id' => $seccion->id]);

        $this->actingAs($this->user)
            ->delete(route('admin.cob.documento-secciones.destroy', $seccion))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('cob_documento_secciones', ['id' => $seccion->id]);
    });
});

describe('documentación por proyecto', function () {
    test('la página del proyecto incluye las secciones activas', function () {
        DocumentoSeccion::factory()->create(['activo' => true]);
        DocumentoSeccion::factory()->create(['activo' => false]);
        $proyecto = Proyecto::factory()->create();

        $this->actingAs($this->user)
            ->get(route('admin.cob.proyectos.show', $proyecto))
            ->assertInertia(fn ($page) => $page->has('documentoSecciones', 1));
    });

    test('crea carpeta raíz y subcarpeta', function () {
        $proyecto = Proyecto::factory()->create();
        $seccion = DocumentoSeccion::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.documentos.carpetas.store', $proyecto), [
                'seccion_id' => $seccion->id,
                'nombre' => 'Carpeta raíz',
            ])
            ->assertRedirect();

        $raiz = DocumentoCarpeta::where('proyecto_id', $proyecto->id)->first();
        expect($raiz->parent_id)->toBeNull();

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.documentos.carpetas.store', $proyecto), [
                'seccion_id' => $seccion->id,
                'parent_id' => $raiz->id,
                'nombre' => 'Subcarpeta',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cob_documento_carpetas', [
            'proyecto_id' => $proyecto->id,
            'parent_id' => $raiz->id,
            'nombre' => 'Subcarpeta',
        ]);
    });

    test('sube archivos a una sección', function () {
        Storage::fake('local');
        $proyecto = Proyecto::factory()->create();
        $seccion = DocumentoSeccion::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.documentos.archivos.store', $proyecto), [
                'seccion_id' => $seccion->id,
                'archivos' => [UploadedFile::fake()->create('contrato.pdf', 120, 'application/pdf')],
            ])
            ->assertRedirect();

        $archivo = DocumentoArchivo::first();
        expect($archivo->nombre_original)->toBe('contrato.pdf');
        expect($archivo->carpeta_id)->toBeNull();
        Storage::disk('local')->assertExists($archivo->path);
    });

    test('rechaza tipos de archivo no permitidos', function () {
        Storage::fake('local');
        $proyecto = Proyecto::factory()->create();
        $seccion = DocumentoSeccion::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.documentos.archivos.store', $proyecto), [
                'seccion_id' => $seccion->id,
                'archivos' => [UploadedFile::fake()->create('virus.exe', 10)],
            ])
            ->assertSessionHasErrors('archivos.0');
    });

    test('eliminar carpeta borra los archivos físicos', function () {
        Storage::fake('local');
        $proyecto = Proyecto::factory()->create();
        $seccion = DocumentoSeccion::factory()->create();
        $carpeta = DocumentoCarpeta::factory()->create(['proyecto_id' => $proyecto->id, 'seccion_id' => $seccion->id]);

        $path = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf')->store('cob/documentos/test', 'local');
        $archivo = DocumentoArchivo::factory()->create([
            'proyecto_id' => $proyecto->id,
            'seccion_id' => $seccion->id,
            'carpeta_id' => $carpeta->id,
            'path' => $path,
        ]);

        $this->actingAs($this->user)
            ->delete(route('admin.cob.proyectos.documentos.carpetas.destroy', [$proyecto, $carpeta]))
            ->assertRedirect();

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('cob_documento_carpetas', ['id' => $carpeta->id]);
        expect(DocumentoArchivo::find($archivo->id))->toBeNull();
    });

    test('descarga un archivo', function () {
        Storage::fake('local');
        $proyecto = Proyecto::factory()->create();
        $seccion = DocumentoSeccion::factory()->create();
        $path = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf')->store('cob/documentos/test', 'local');
        $archivo = DocumentoArchivo::factory()->create([
            'proyecto_id' => $proyecto->id,
            'seccion_id' => $seccion->id,
            'path' => $path,
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.cob.documentos.archivos.descargar', $archivo))
            ->assertOk();
    });
});

describe('estatus de sección por proyecto', function () {
    test('la página del proyecto trae el estatus y visibilidad de cada sección (defaults)', function () {
        DocumentoSeccion::factory()->create(['activo' => true]);
        $proyecto = Proyecto::factory()->create();

        $this->actingAs($this->user)
            ->get(route('admin.cob.proyectos.show', $proyecto))
            ->assertInertia(fn ($page) => $page
                ->has('documentoSecciones', 1)
                ->where('documentoSecciones.0.estatus', 'pendiente')
                ->where('documentoSecciones.0.visible', true)
            );
    });

    test('ocultar una sección es por proyecto y no afecta a otros', function () {
        $seccion = DocumentoSeccion::factory()->create(['activo' => true]);
        $proyectoA = Proyecto::factory()->create();
        $proyectoB = Proyecto::factory()->create();

        $this->actingAs($this->user)
            ->put(route('admin.cob.proyectos.secciones.visibilidad', [$proyectoA, $seccion]), ['visible' => false])
            ->assertRedirect();

        $this->assertDatabaseHas('cob_documento_seccion_proyecto', [
            'proyecto_id' => $proyectoA->id,
            'seccion_id' => $seccion->id,
            'visible' => false,
        ]);

        // El proyecto A la ve oculta; el proyecto B la sigue viendo visible.
        $this->actingAs($this->user)
            ->get(route('admin.cob.proyectos.show', $proyectoA))
            ->assertInertia(fn ($page) => $page->where('documentoSecciones.0.visible', false));

        $this->actingAs($this->user)
            ->get(route('admin.cob.proyectos.show', $proyectoB))
            ->assertInertia(fn ($page) => $page->where('documentoSecciones.0.visible', true));
    });

    test('el estatus y la visibilidad conviven en el mismo registro pivote', function () {
        $seccion = DocumentoSeccion::factory()->create();
        $proyecto = Proyecto::factory()->create();

        $this->actingAs($this->user)
            ->put(route('admin.cob.proyectos.secciones.estatus', [$proyecto, $seccion]), ['estatus' => 'completado'])
            ->assertRedirect();
        $this->actingAs($this->user)
            ->put(route('admin.cob.proyectos.secciones.visibilidad', [$proyecto, $seccion]), ['visible' => false])
            ->assertRedirect();

        expect(\App\Models\Cob\DocumentoSeccionProyecto::where('proyecto_id', $proyecto->id)->where('seccion_id', $seccion->id)->count())->toBe(1);
        $this->assertDatabaseHas('cob_documento_seccion_proyecto', [
            'proyecto_id' => $proyecto->id,
            'seccion_id' => $seccion->id,
            'estatus' => 'completado',
            'visible' => false,
        ]);
    });

    test('marca una sección como completada para el proyecto (upsert idempotente)', function () {
        $proyecto = Proyecto::factory()->create();
        $seccion = DocumentoSeccion::factory()->create();

        $this->actingAs($this->user)
            ->put(route('admin.cob.proyectos.secciones.estatus', [$proyecto, $seccion]), ['estatus' => 'completado'])
            ->assertRedirect();

        $this->assertDatabaseHas('cob_documento_seccion_proyecto', [
            'proyecto_id' => $proyecto->id,
            'seccion_id' => $seccion->id,
            'estatus' => 'completado',
        ]);

        // Segundo cambio: actualiza el mismo registro, no crea otro.
        $this->actingAs($this->user)
            ->put(route('admin.cob.proyectos.secciones.estatus', [$proyecto, $seccion]), ['estatus' => 'pendiente'])
            ->assertRedirect();

        expect(\App\Models\Cob\DocumentoSeccionProyecto::where('proyecto_id', $proyecto->id)->where('seccion_id', $seccion->id)->count())->toBe(1);
        $this->assertDatabaseHas('cob_documento_seccion_proyecto', [
            'proyecto_id' => $proyecto->id,
            'seccion_id' => $seccion->id,
            'estatus' => 'pendiente',
        ]);
    });

    test('rechaza un estatus inválido', function () {
        $proyecto = Proyecto::factory()->create();
        $seccion = DocumentoSeccion::factory()->create();

        $this->actingAs($this->user)
            ->put(route('admin.cob.proyectos.secciones.estatus', [$proyecto, $seccion]), ['estatus' => 'archivado'])
            ->assertSessionHasErrors('estatus');
    });
});
