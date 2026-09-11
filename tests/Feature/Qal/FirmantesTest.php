<?php

use App\Enums\Qal\OrigenFirmante;
use App\Models\Qal\Firmante;
use App\Models\User;
use App\Services\Qal\FirmasDeFormato;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

/**
 * El orden de firma de los formatos PDF de Calidad: el catálogo que lo
 * administra y lo que cada formato recibe para estampar.
 */
function usuarioDeFirmantes(array $permisos): User
{
    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

test('nacen dos firmas: quien elaboro y la jefatura de calidad sin persona', function () {
    $firmantes = Firmante::query()->orderBy('orden')->get();

    expect($firmantes)->toHaveCount(2)
        ->and($firmantes[0]->etiqueta)->toBe('Elaboró')
        ->and($firmantes[0]->origen)->toBe(OrigenFirmante::Creador)
        ->and($firmantes[1]->etiqueta)->toBe('Revisó')
        ->and($firmantes[1]->cargo)->toBe('Jefatura de calidad')
        ->and($firmantes[1]->origen)->toBe(OrigenFirmante::Usuario)
        ->and($firmantes[1]->usuario_id)->toBeNull();
});

test('la pestana llega con las firmas y la lista de usuarios solo a quien las edita', function () {
    $jefa = User::factory()->create(['name' => 'Jefa de calidad', 'firma_path' => 'firmas/jefa.png']);
    Firmante::query()->where('orden', 2)->update(['usuario_id' => $jefa->id]);

    $this->actingAs(usuarioDeFirmantes(['qal.firmantes.ver']))
        ->get(route('admin.qal.catalogos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('firmantes', 2)
            ->where('firmantes.1.usuario', 'Jefa de calidad')
            ->where('firmantes.1.tiene_rubrica', true)
            ->where('usuariosFirmantes', [])
            ->has('origenesFirmante', 2));

    $this->actingAs(usuarioDeFirmantes(['qal.firmantes.ver', 'qal.firmantes.editar']))
        ->get(route('admin.qal.catalogos.index'))
        ->assertInertia(fn ($page) => $page->where('usuariosFirmantes', fn ($usuarios) => collect($usuarios)->contains('nombre', 'Jefa de calidad')));
});

test('una firma nueva va al final y la de quien elaboro descarta la persona', function () {
    $otro = User::factory()->create();

    $this->actingAs(usuarioDeFirmantes(['qal.firmantes.editar']))
        ->post(route('admin.qal.catalogos.firmantes.store'), [
            'etiqueta' => 'Aprobó',
            'cargo' => 'Gerencia de planta',
            'origen' => 'creador',
            'usuario_id' => $otro->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $nueva = Firmante::query()->firstWhere('etiqueta', 'Aprobó');

    expect($nueva->orden)->toBe(3)
        ->and($nueva->origen)->toBe(OrigenFirmante::Creador)
        ->and($nueva->usuario_id)->toBeNull();
});

test('se elige a la persona fija de un lugar', function () {
    $jefa = User::factory()->create();
    $revision = Firmante::query()->firstWhere('orden', 2);

    $this->actingAs(usuarioDeFirmantes(['qal.firmantes.editar']))
        ->put(route('admin.qal.catalogos.firmantes.update', $revision), [
            'etiqueta' => 'Revisó',
            'cargo' => 'Jefatura de calidad',
            'origen' => 'usuario',
            'usuario_id' => $jefa->id,
        ])
        ->assertSessionHasNoErrors();

    expect($revision->fresh()->usuario_id)->toBe($jefa->id);
});

test('rechaza un origen desconocido y un usuario que no existe', function () {
    $this->actingAs(usuarioDeFirmantes(['qal.firmantes.editar']))
        ->post(route('admin.qal.catalogos.firmantes.store'), [
            'etiqueta' => 'Aprobó',
            'cargo' => 'Gerencia',
            'origen' => 'cualquiera',
        ])
        ->assertSessionHasErrors('origen');

    $this->post(route('admin.qal.catalogos.firmantes.store'), [
        'etiqueta' => 'Aprobó',
        'cargo' => 'Gerencia',
        'origen' => 'usuario',
        'usuario_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
    ])->assertSessionHasErrors('usuario_id');

    expect(Firmante::count())->toBe(2);
});

test('verlas no alcanza para cambiarlas', function () {
    $this->actingAs(usuarioDeFirmantes(['qal.firmantes.ver']))
        ->post(route('admin.qal.catalogos.firmantes.store'), ['etiqueta' => 'Aprobó', 'cargo' => 'Gerencia', 'origen' => 'creador'])
        ->assertForbidden();

    $this->delete(route('admin.qal.catalogos.firmantes.destroy', Firmante::first()))->assertForbidden();
});

test('reordenar pide todos los ids y los renumera desde uno', function () {
    $tercera = Firmante::factory()->create(['orden' => 3]);
    [$elaboro, $reviso] = Firmante::query()->where('orden', '<', 3)->orderBy('orden')->get();
    $usuario = usuarioDeFirmantes(['qal.firmantes.editar']);

    $this->actingAs($usuario)
        ->put(route('admin.qal.catalogos.firmantes.reordenar'), ['ids' => [$elaboro->id, $reviso->id]])
        ->assertSessionHasErrors('ids');

    $this->put(route('admin.qal.catalogos.firmantes.reordenar'), ['ids' => [$tercera->id, $elaboro->id, $reviso->id]])
        ->assertSessionHasNoErrors();

    expect(Firmante::query()->orderBy('orden')->pluck('id')->all())->toBe([$tercera->id, $elaboro->id, $reviso->id])
        ->and(Firmante::query()->orderBy('orden')->pluck('orden')->all())->toBe([1, 2, 3]);
});

test('quitar una firma cierra el hueco en el orden', function () {
    $this->actingAs(usuarioDeFirmantes(['qal.firmantes.editar']))
        ->delete(route('admin.qal.catalogos.firmantes.destroy', Firmante::query()->firstWhere('orden', 1)))
        ->assertRedirect();

    expect(Firmante::query()->pluck('orden')->all())->toBe([1])
        ->and(Firmante::first()->etiqueta)->toBe('Revisó');
});

test('el formato recibe a quien elaboro y a la persona fija con sus rubricas', function () {
    Storage::fake('public');
    Storage::disk('public')->put('firmas/inspector.png', 'png');

    $inspector = User::factory()->create(['name' => 'Inspector Uno', 'firma_path' => 'firmas/inspector.png']);
    // Tiene ruta guardada pero el archivo ya no está: sale sin imagen.
    $jefa = User::factory()->create(['name' => 'Jefa Dos', 'firma_path' => 'firmas/perdida.png']);
    Firmante::query()->where('orden', 2)->update(['usuario_id' => $jefa->id]);

    $firmas = app(FirmasDeFormato::class)->para($inspector);

    expect($firmas)->toHaveCount(2)
        ->and($firmas[0])->toMatchArray(['etiqueta' => 'Elaboró', 'nombre' => 'Inspector Uno'])
        ->and($firmas[0]['rubrica'])->toBe(Storage::disk('public')->path('firmas/inspector.png'))
        ->and($firmas[1])->toMatchArray(['etiqueta' => 'Revisó', 'cargo' => 'Jefatura de calidad', 'nombre' => 'Jefa Dos', 'rubrica' => null]);
});

test('sin creador ni persona elegida los lugares salen en blanco pero no se quitan', function () {
    $firmas = app(FirmasDeFormato::class)->para(null);

    expect($firmas)->toHaveCount(2)
        ->and(array_column($firmas, 'nombre'))->toBe([null, null])
        ->and(array_column($firmas, 'rubrica'))->toBe([null, null]);

    $html = view('pdf.partials.firmas-qal', ['firmas' => $firmas])->render();

    expect($html)->toContain('Elaboró')
        ->toContain('Jefatura de calidad')
        ->not->toContain('<img');
});

test('la hoja estampa la rubrica sobre la raya', function () {
    $html = view('pdf.partials.firmas-qal', ['firmas' => [
        ['etiqueta' => 'Elaboró', 'cargo' => 'Inspector de calidad', 'nombre' => 'Inspector Uno', 'rubrica' => '/ruta/rubrica.png'],
    ]])->render();

    expect($html)->toContain('src="/ruta/rubrica.png"')->toContain('Inspector Uno');
});
