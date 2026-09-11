<?php

use App\Enums\Qal\EstatusDossier;
use App\Models\Obra;
use App\Models\Qal\Dossier;
use App\Models\Qal\DossierArchivo;
use App\Models\Qal\DossierPlantilla;
use App\Models\Qal\DossierSeccion;
use App\Models\User;
use App\Services\Qal\Dosier\ArbolDeSecciones;
use App\Services\Qal\Dosier\UnidorDePdf;
use App\Services\Qal\Dosier\UnidorFpdi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

/**
 * El dosier de una obra: nace de una plantilla, se le suben PDF por sección y
 * «Descargar» los une con portada, índice y separadores.
 */
beforeEach(fn () => Storage::fake(DossierArchivo::DISCO));

function jefeDeDosier(array $permisos = ['qal.dossier.ver', 'qal.dossier.editar']): User
{
    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

/** Un PDF que FPDI abre: los que genera el propio sistema. */
function pdfQueSeUne(string $nombre): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nombre, Pdf::loadHTML("<p>{$nombre}</p>")->output());
}

/** Empieza como PDF —pasa la validación— pero el motor no lo puede abrir. */
function pdfQueNoSeUne(string $nombre): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.5\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< /Type /ObjStm >>\nstream\nxyz\nendstream\nendobj\n%%EOF");
}

function dosierDePlantilla(string $plantilla = 'Tipo C · Reducido'): Dossier
{
    $obra = Obra::factory()->create();
    $usuario = jefeDeDosier();

    test()->actingAs($usuario)->post(route('admin.qal.dosier.store'), [
        'obra_id' => $obra->id,
        'plantilla_id' => DossierPlantilla::query()->where('nombre', $plantilla)->value('id'),
    ]);

    return Dossier::query()->where('obra_id', $obra->id)->firstOrFail();
}

function seccionLlamada(Dossier $dossier, string $titulo): DossierSeccion
{
    return $dossier->secciones()->where('titulo', $titulo)->firstOrFail();
}

test('un dosier nace copiando el arbol de su plantilla y cambiar la plantilla despues no lo toca', function () {
    $obra = Obra::factory()->create();
    $tipoC = DossierPlantilla::query()->where('nombre', 'Tipo C · Reducido')->firstOrFail();
    $arboles = app(ArbolDeSecciones::class);

    $this->actingAs(jefeDeDosier())
        ->post(route('admin.qal.dosier.store'), ['obra_id' => $obra->id, 'plantilla_id' => $tipoC->id])
        ->assertRedirect(route('admin.qal.dosier.show', Dossier::query()->firstWhere('obra_id', $obra->id)));

    $dossier = Dossier::query()->firstWhere('obra_id', $obra->id);
    $titulos = fn (array $arbol): array => array_column($arboles->aplanar($arbol), 'titulo', 'numero');

    expect($dossier->plantilla_nombre)->toBe('Tipo C · Reducido')
        ->and($dossier->estatus)->toBe(EstatusDossier::Borrador)
        ->and($titulos($dossier->arbol()))->toBe($titulos($tipoC->arbol()));

    $arboles->guardar($tipoC->secciones(), [['titulo' => 'OTRA COSA', 'hijos' => []]]);

    expect($titulos($dossier->fresh()->arbol()))->not->toBe(['1' => 'OTRA COSA'])
        ->and($dossier->secciones()->count())->toBe(16);
});

test('una obra tiene un solo dosier y no nace de una plantilla desactivada', function () {
    $dossier = dosierDePlantilla();
    $inactiva = DossierPlantilla::factory()->inactiva()->create();

    $this->post(route('admin.qal.dosier.store'), ['obra_id' => $dossier->obra_id, 'plantilla_id' => $inactiva->id])
        ->assertSessionHasErrors(['obra_id', 'plantilla_id']);
});

test('se suben varios pdf a una seccion y el que no se puede unir queda marcado', function () {
    $dossier = dosierDePlantilla();
    $seccion = seccionLlamada($dossier, 'PLACAS');

    $this->post(route('admin.qal.dosier.archivos.store', [$dossier, $seccion]), [
        'archivos' => [pdfQueSeUne('coladas-1.pdf'), pdfQueSeUne('coladas-2.pdf'), pdfQueNoSeUne('escaneo.pdf')],
    ])->assertSessionHasNoErrors();

    $archivos = $seccion->archivos()->get();

    expect($archivos->pluck('nombre_original')->all())->toBe(['coladas-1.pdf', 'coladas-2.pdf', 'escaneo.pdf'])
        ->and($archivos->pluck('orden')->all())->toBe([1, 2, 3])
        ->and($archivos->pluck('compatible')->all())->toBe([true, true, false])
        ->and($archivos[0]->paginas)->toBe(1)
        ->and($archivos[2]->paginas)->toBeNull()
        ->and(session('success'))->toContain('1 no se pueden unir');
    Storage::disk(DossierArchivo::DISCO)->assertExists($archivos->pluck('path')->all());
    expect($archivos[0]->path)->toStartWith("qal/dosier/{$dossier->id}/{$seccion->id}/");
});

test('solo se suben pdf', function () {
    $dossier = dosierDePlantilla();

    $this->post(route('admin.qal.dosier.archivos.store', [$dossier, seccionLlamada($dossier, 'PLACAS')]), [
        'archivos' => [UploadedFile::fake()->create('foto.jpg', 100, 'image/jpeg')],
    ])->assertSessionHasErrors('archivos.0');
});

test('los pdf se reordenan y al quitar uno se borra del disco y se cierra el hueco', function () {
    $dossier = dosierDePlantilla();
    $seccion = seccionLlamada($dossier, 'PLACAS');
    $this->post(route('admin.qal.dosier.archivos.store', [$dossier, $seccion]), [
        'archivos' => [pdfQueSeUne('a.pdf'), pdfQueSeUne('b.pdf'), pdfQueSeUne('c.pdf')],
    ]);
    [$a, $b, $c] = $seccion->archivos()->get();

    $this->put(route('admin.qal.dosier.archivos.reordenar', [$dossier, $seccion]), ['ids' => [$c->id, $a->id, $b->id]])
        ->assertSessionHasNoErrors();
    $this->delete(route('admin.qal.dosier.archivos.destroy', [$dossier, $a]))->assertRedirect();

    expect($seccion->archivos()->pluck('nombre_original')->all())->toBe(['c.pdf', 'b.pdf'])
        ->and($seccion->archivos()->pluck('orden')->all())->toBe([1, 2]);
    Storage::disk(DossierArchivo::DISCO)->assertMissing($a->path);
});

test('entregar fija la fecha y volver a revision la limpia', function () {
    $dossier = dosierDePlantilla();

    $this->put(route('admin.qal.dosier.update', $dossier), ['estatus' => 'entregado', 'notas' => 'Se mandó por paquetería'])->assertRedirect();
    expect($dossier->fresh()->entregado_at)->not->toBeNull()
        ->and($dossier->fresh()->notas)->toBe('Se mandó por paquetería');

    $this->put(route('admin.qal.dosier.update', $dossier), ['estatus' => 'en_revision']);
    expect($dossier->fresh()->entregado_at)->toBeNull();
});

test('descargar une portada, separadores y los pdf de cada seccion en su orden, sin los que no se pueden unir', function () {
    $dossier = dosierDePlantilla();
    $placas = seccionLlamada($dossier, 'PLACAS');
    $perfiles = seccionLlamada($dossier, 'PERFILES');
    $this->post(route('admin.qal.dosier.archivos.store', [$dossier, $placas]), ['archivos' => [pdfQueSeUne('p1.pdf'), pdfQueNoSeUne('escaneo.pdf')]]);
    $this->post(route('admin.qal.dosier.archivos.store', [$dossier, $perfiles]), ['archivos' => [pdfQueSeUne('f1.pdf'), pdfQueSeUne('f2.pdf')]]);
    $rutaDe = fn (string $nombre): string => DossierArchivo::query()->firstWhere('nombre_original', $nombre)->rutaAbsoluta();

    $unidor = new class implements UnidorDePdf
    {
        /** @var list<string> */
        public array $rutas = [];

        public function paginas(string $ruta): ?int
        {
            return 1;
        }

        public function unir(array $rutas, string $destino): void
        {
            $this->rutas = $rutas;
            file_put_contents($destino, '%PDF-unido');
        }
    };
    $this->app->instance(UnidorDePdf::class, $unidor);

    $respuesta = $this->get(route('admin.qal.dosier.descargar', $dossier));

    $respuesta->assertOk();
    expect($respuesta->headers->get('content-disposition'))->toContain('attachment')
        // portada · separador de 1.1.1 · p1 · separador de 1.1.2 · f1 · f2
        ->and($unidor->rutas)->toHaveCount(6)
        ->and(array_slice($unidor->rutas, 2, 1))->toBe([$rutaDe('p1.pdf')])
        ->and(array_slice($unidor->rutas, 4))->toBe([$rutaDe('f1.pdf'), $rutaDe('f2.pdf')])
        ->and($unidor->rutas)->not->toContain($rutaDe('escaneo.pdf'));
});

test('el motor provisional une los pdf del sistema y no abre los que no puede', function () {
    $carpeta = sys_get_temp_dir();
    $uno = tempnam($carpeta, 'uno_');
    $dos = tempnam($carpeta, 'dos_');
    file_put_contents($uno, Pdf::loadHTML('<p>uno</p>')->output());
    file_put_contents($dos, Pdf::loadHTML('<p>dos</p><div style="page-break-before: always">tres</div>')->output());
    $malo = tempnam($carpeta, 'malo_');
    file_put_contents($malo, pdfQueNoSeUne('x.pdf')->getContent());
    $destino = tempnam($carpeta, 'unido_');

    $motor = new UnidorFpdi;
    $motor->unir([$uno, $dos], $destino);

    expect($motor->paginas($destino))->toBe(3)
        ->and($motor->paginas($malo))->toBeNull();

    array_map('unlink', [$uno, $dos, $malo, $destino]);
});

test('un pdf se ve con permiso y solo desde su dosier', function () {
    $dossier = dosierDePlantilla();
    $otro = dosierDePlantilla();
    $this->post(route('admin.qal.dosier.archivos.store', [$dossier, seccionLlamada($dossier, 'PLACAS')]), ['archivos' => [pdfQueSeUne('a.pdf')]]);
    $archivo = DossierArchivo::query()->firstOrFail();

    $this->actingAs(jefeDeDosier(['qal.dossier.ver']))
        ->get(route('admin.qal.dosier.archivos.ver', [$dossier, $archivo]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    $this->get(route('admin.qal.dosier.archivos.ver', [$otro, $archivo]))->assertNotFound();
    $this->post(route('admin.qal.dosier.archivos.store', [$dossier, seccionLlamada($dossier, 'PLACAS')]), ['archivos' => [pdfQueSeUne('b.pdf')]])
        ->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('admin.qal.dosier.archivos.ver', [$dossier, $archivo]))->assertForbidden();
});

test('quitar del arbol una seccion con pdf los borra del disco', function () {
    $dossier = dosierDePlantilla();
    $placas = seccionLlamada($dossier, 'PLACAS');
    $this->post(route('admin.qal.dosier.archivos.store', [$dossier, $placas]), ['archivos' => [pdfQueSeUne('a.pdf')]]);
    $archivo = DossierArchivo::query()->firstOrFail();

    // Sólo queda la primera sección principal, sin sus hijas.
    $primera = $dossier->arbol()[0];
    $this->put(route('admin.qal.dosier.secciones', $dossier), ['arbol' => [[...$primera, 'hijos' => []]]])->assertSessionHasNoErrors();

    expect($dossier->secciones()->count())->toBe(1)
        ->and(DossierArchivo::query()->count())->toBe(0);
    Storage::disk(DossierArchivo::DISCO)->assertMissing($archivo->path);
});

test('solo se borra un dosier en borrador y se lleva sus archivos', function () {
    $dossier = dosierDePlantilla();
    $this->post(route('admin.qal.dosier.archivos.store', [$dossier, seccionLlamada($dossier, 'PLACAS')]), ['archivos' => [pdfQueSeUne('a.pdf')]]);
    $archivo = DossierArchivo::query()->firstOrFail();
    $dossier->update(['estatus' => EstatusDossier::Entregado]);

    $this->delete(route('admin.qal.dosier.destroy', $dossier))->assertSessionHasErrors('dosier');
    expect(Dossier::query()->whereKey($dossier->id)->exists())->toBeTrue();

    $dossier->update(['estatus' => EstatusDossier::Borrador]);
    $this->delete(route('admin.qal.dosier.destroy', $dossier))->assertRedirect(route('admin.qal.dosier.index'));

    expect(Dossier::query()->whereKey($dossier->id)->exists())->toBeFalse();
    Storage::disk(DossierArchivo::DISCO)->assertMissing($archivo->path);
});

test('la lista y el editor llegan con el avance y los archivos de cada seccion', function () {
    $dossier = dosierDePlantilla();
    $placas = seccionLlamada($dossier, 'PLACAS');
    $this->post(route('admin.qal.dosier.archivos.store', [$dossier, $placas]), ['archivos' => [pdfQueSeUne('a.pdf'), pdfQueNoSeUne('b.pdf')]]);

    $this->get(route('admin.qal.dosier.index'))
        ->assertInertia(fn ($page) => $page
            ->where('dosieres.0.id', $dossier->id)
            ->where('dosieres.0.con_archivo', 1)
            ->where('dosieres.0.secciones', 16)
            ->where('dosieres.0.archivos', 2)
            ->where('dosieres.0.no_compatibles', 1));

    $this->get(route('admin.qal.dosier.show', $dossier))
        ->assertInertia(fn ($page) => $page
            ->component('admin/calidad/dosier/show')
            ->where('arbol.0.hijos.0.hijos.0.titulo', 'PLACAS')
            ->has('archivos', 2)
            ->where('archivos.0.seccion_id', $placas->id)
            ->where('archivos.1.compatible', false));
});
