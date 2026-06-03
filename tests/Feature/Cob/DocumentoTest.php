<?php

use App\Models\Cob\DocumentoArchivo;
use App\Models\Cob\DocumentoCarpeta;
use App\Models\Cob\DocumentoSeccion;
use App\Models\Obra;
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

describe('documentación por obra', function () {
    test('show incluye las secciones activas', function () {
        DocumentoSeccion::factory()->create(['activo' => true]);
        DocumentoSeccion::factory()->create(['activo' => false]);
        $obra = Obra::factory()->create();

        $this->actingAs($this->user)
            ->get(route('admin.cob.obras.show', $obra))
            ->assertInertia(fn ($page) => $page->has('documentoSecciones', 1));
    });

    test('crea carpeta raíz y subcarpeta', function () {
        $obra = Obra::factory()->create();
        $seccion = DocumentoSeccion::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cob.obras.documentos.carpetas.store', $obra), [
                'seccion_id' => $seccion->id,
                'nombre' => 'Carpeta raíz',
            ])
            ->assertRedirect();

        $raiz = DocumentoCarpeta::where('obra_id', $obra->id)->first();
        expect($raiz->parent_id)->toBeNull();

        $this->actingAs($this->user)
            ->post(route('admin.cob.obras.documentos.carpetas.store', $obra), [
                'seccion_id' => $seccion->id,
                'parent_id' => $raiz->id,
                'nombre' => 'Subcarpeta',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cob_documento_carpetas', [
            'obra_id' => $obra->id,
            'parent_id' => $raiz->id,
            'nombre' => 'Subcarpeta',
        ]);
    });

    test('sube archivos a una sección', function () {
        Storage::fake('local');
        $obra = Obra::factory()->create();
        $seccion = DocumentoSeccion::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cob.obras.documentos.archivos.store', $obra), [
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
        $obra = Obra::factory()->create();
        $seccion = DocumentoSeccion::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cob.obras.documentos.archivos.store', $obra), [
                'seccion_id' => $seccion->id,
                'archivos' => [UploadedFile::fake()->create('virus.exe', 10)],
            ])
            ->assertSessionHasErrors('archivos.0');
    });

    test('eliminar carpeta borra los archivos físicos', function () {
        Storage::fake('local');
        $obra = Obra::factory()->create();
        $seccion = DocumentoSeccion::factory()->create();
        $carpeta = DocumentoCarpeta::factory()->create(['obra_id' => $obra->id, 'seccion_id' => $seccion->id]);

        $path = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf')->store('cob/documentos/test', 'local');
        $archivo = DocumentoArchivo::factory()->create([
            'obra_id' => $obra->id,
            'seccion_id' => $seccion->id,
            'carpeta_id' => $carpeta->id,
            'path' => $path,
        ]);

        $this->actingAs($this->user)
            ->delete(route('admin.cob.obras.documentos.carpetas.destroy', [$obra, $carpeta]))
            ->assertRedirect();

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('cob_documento_carpetas', ['id' => $carpeta->id]);
        expect(DocumentoArchivo::find($archivo->id))->toBeNull();
    });

    test('descarga un archivo', function () {
        Storage::fake('local');
        $obra = Obra::factory()->create();
        $seccion = DocumentoSeccion::factory()->create();
        $path = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf')->store('cob/documentos/test', 'local');
        $archivo = DocumentoArchivo::factory()->create([
            'obra_id' => $obra->id,
            'seccion_id' => $seccion->id,
            'path' => $path,
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.cob.documentos.archivos.descargar', $archivo))
            ->assertOk();
    });
});
