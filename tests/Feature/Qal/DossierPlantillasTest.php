<?php

use App\Models\Qal\DossierPlantilla;
use App\Models\User;
use App\Services\Qal\Dosier\ArbolDeSecciones;
use Spatie\Permission\Models\Permission;

/**
 * El catálogo de plantillas del dosier: las cuatro de Steelex con que nace, y
 * el árbol que se guarda entero desde el editor.
 */
function editorDeDosier(array $permisos = ['qal.dossier.ver', 'qal.dossier.editar']): User
{
    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

/**
 * @return array<string, string> número ⇒ título
 */
function indiceDe(DossierPlantilla $plantilla): array
{
    return collect(app(ArbolDeSecciones::class)->aplanar($plantilla->fresh()->arbol()))->pluck('titulo', 'numero')->all();
}

function plantillaLlamada(string $nombre): DossierPlantilla
{
    return DossierPlantilla::query()->where('nombre', $nombre)->firstOrFail();
}

test('nacen las cuatro plantillas de steelex con el numero calculado por posicion', function () {
    expect(DossierPlantilla::query()->orderBy('nombre')->pluck('nombre')->all())
        ->toBe(['Estándar', 'Tipo A · Completo', 'Tipo B · Estándar', 'Tipo C · Reducido']);

    $indice = indiceDe(plantillaLlamada('Tipo A · Completo'));

    expect($indice['1.1.3.2'])->toBe('REPORTE DE PRUEBA DE TENSIÓN')
        ->and($indice['2'])->toBe('ACREDITACIÓN DEL PERSONAL DE CALIDAD')
        // El índice real repetía el «2»: los soldadores quedan en el 3 y lo demás corre.
        ->and($indice['3'])->toBe('REGISTRO DE CALIFICACIÓN DE SOLDADORES (WPQR)')
        ->and($indice['11.5'])->toBe('PRUEBAS DE ADHERENCIA');
});

test('la pantalla abre en dosieres o en el catalogo y pide su permiso', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.qal.dosier.index'))->assertForbidden();

    $this->actingAs(editorDeDosier(['qal.dossier.ver']))
        ->get(route('admin.qal.dosier.index', ['tab' => 'catalogo']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/calidad/dosier/index')
            ->where('tab', 'catalogo')
            ->has('plantillas', 4)
            ->where('plantillas.0.arbol.0.numero', '1'));
});

test('una plantilla nueva puede partir de otra y copia su arbol entero', function () {
    $tipoC = plantillaLlamada('Tipo C · Reducido');

    $this->actingAs(editorDeDosier())
        ->post(route('admin.qal.dosier.plantillas.store'), ['nombre' => 'Naves industriales', 'desde' => $tipoC->id])
        ->assertRedirect(route('admin.qal.dosier.index', ['tab' => 'catalogo', 'plantilla' => DossierPlantilla::max('id')]));

    $copia = plantillaLlamada('Naves industriales');

    expect(indiceDe($copia))->toBe(indiceDe($tipoC))
        ->and($copia->secciones()->count())->toBe($tipoC->secciones()->count())
        ->and($copia->secciones()->pluck('id')->intersect($tipoC->secciones()->pluck('id')))->toBeEmpty();
});

test('guardar el arbol renombra, reordena, agrega y quita secciones', function () {
    $tipoC = plantillaLlamada('Tipo C · Reducido');
    $arbol = $tipoC->arbol();

    // Trazabilidad sube al primer lugar, con una subsección nueva; los materiales se renombran;
    // recubrimientos (con sus cuatro hijas) desaparece.
    [$materiales, $trazabilidad, $fabricacion, $pnd] = $arbol;
    $materiales['titulo'] = 'CERTIFICADOS DE MATERIALES';
    $trazabilidad['hijos'] = [['id' => null, 'titulo' => 'REPORTE DE COLADAS', 'nota' => 'Por lote de acero', 'hijos' => []]];

    $this->actingAs(editorDeDosier())
        ->put(route('admin.qal.dosier.plantillas.arbol', $tipoC), ['arbol' => [$trazabilidad, $materiales, $fabricacion, $pnd]])
        ->assertSessionHasNoErrors();

    $indice = indiceDe($tipoC);

    expect($indice['1'])->toBe('TRAZABILIDAD DE MATERIALES')
        ->and($indice['1.1'])->toBe('REPORTE DE COLADAS')
        ->and($indice['2'])->toBe('CERTIFICADOS DE MATERIALES')
        ->and($indice['2.1.2'])->toBe('PERFILES')
        ->and($indice)->not->toHaveKey('5')
        ->and($tipoC->secciones()->count())->toBe(12)
        // La sección que se movió conserva su id: no se borró y volvió a crear.
        ->and($tipoC->secciones()->whereKey($trazabilidad['id'])->value('orden'))->toBe(1);
});

test('un id de otra plantilla se toma como seccion nueva y no toca la otra', function () {
    $tipoA = plantillaLlamada('Tipo A · Completo');
    $tipoC = plantillaLlamada('Tipo C · Reducido');
    $ajena = $tipoA->arbol()[0];
    $antes = indiceDe($tipoA);

    $this->actingAs(editorDeDosier())
        ->put(route('admin.qal.dosier.plantillas.arbol', $tipoC), ['arbol' => [['id' => $ajena['id'], 'titulo' => 'SECCIÓN ÚNICA', 'hijos' => []]]])
        ->assertSessionHasNoErrors();

    expect(indiceDe($tipoC))->toBe(['1' => 'SECCIÓN ÚNICA'])
        ->and(indiceDe($tipoA))->toBe($antes);
});

test('el arbol se rechaza con una seccion sin titulo o mas hondo de cuatro niveles', function () {
    $plantilla = plantillaLlamada('Estándar');
    $this->actingAs(editorDeDosier());

    $this->put(route('admin.qal.dosier.plantillas.arbol', $plantilla), ['arbol' => [
        ['titulo' => 'UNO', 'hijos' => [['titulo' => 'UNO UNO'], ['titulo' => '  ']]],
    ]])->assertSessionHasErrors(['arbol' => 'La sección 1.2 no tiene título.']);

    $nivel = function (int $n) use (&$nivel): array {
        return $n === 0 ? [] : [['titulo' => "NIVEL {$n}", 'hijos' => $nivel($n - 1)]];
    };
    $this->put(route('admin.qal.dosier.plantillas.arbol', $plantilla), ['arbol' => $nivel(5)])
        ->assertSessionHasErrors('arbol');

    expect(indiceDe($plantilla)['1'])->toBe('NORMATIVIDAD');
});

test('quien solo ve el dosier no cambia el catalogo, y una plantilla se desactiva sin borrarse', function () {
    $plantilla = plantillaLlamada('Estándar');

    $this->actingAs(editorDeDosier(['qal.dossier.ver']))
        ->post(route('admin.qal.dosier.plantillas.store'), ['nombre' => 'Otra'])
        ->assertForbidden();
    $this->put(route('admin.qal.dosier.plantillas.arbol', $plantilla), ['arbol' => []])->assertForbidden();

    $this->actingAs(editorDeDosier())->patch(route('admin.qal.dosier.plantillas.toggle', $plantilla))->assertRedirect();

    expect($plantilla->fresh()->activo)->toBeFalse()
        ->and($plantilla->secciones()->count())->toBeGreaterThan(0);
});
