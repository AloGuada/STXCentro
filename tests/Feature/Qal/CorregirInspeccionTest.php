<?php

use App\Models\Concepto;
use App\Models\Media;
use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use App\Models\User;
use Database\Seeders\QalPuntosInspeccionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

/**
 * Corregir, reinspeccionar y borrar una inspección ya guardada.
 *
 * Corregir sobrescribe sin tocar lo que la identifica (folio, número de
 * inspección, pieza). Reinspeccionar es una inspección nueva de la misma pieza.
 * Borrar se lleva la evidencia de disco, que no cuelga por llave foránea.
 */
beforeEach(fn () => $this->seed(QalPuntosInspeccionSeeder::class));

function usuarioQueCorrige(array $permisos = ['qal.inspecciones.crear', 'qal.inspecciones.editar']): User
{
    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

/**
 * @return array<string, mixed>
 */
function primeraParaCorregir(Concepto $concepto, array $cambios = []): array
{
    return [
        'obra_id' => $concepto->obra_id,
        'fase' => '1ª',
        'subtipo' => 'perfil',
        'fecha' => now()->toDateString(),
        'concepto_id' => $concepto->id,
        'consecutivo' => 1,
        'kg' => 100,
        'estatus' => 'rechazado',
        'puntos' => ['p1_dim' => 'Fuera de tol.', 'p1_defl' => 'Fuera de tol.'],
        ...$cambios,
    ];
}

/**
 * @return array<string, mixed>
 */
function pinturaConEvidencia(Pieza $pieza, array $cambios = []): array
{
    return [
        'obra_id' => $pieza->catalogo->obra_id,
        'fase' => '3ª',
        'fecha' => now()->toDateString(),
        'prod_pieza_id' => $pieza->id,
        'kg' => 300,
        'estatus' => 'liberado',
        'pintura' => ['mediciones_visibles' => 5, 'lecturas' => [[4, 4, 4]]],
        'adherencia' => ['resultado' => 'Aceptado', 'tiras' => [
            ['metodo' => 'A', 'clasificacion' => '5A'],
            ['metodo' => 'A', 'clasificacion' => '5A'],
            ['metodo' => 'A', 'clasificacion' => '4A'],
        ]],
        ...$cambios,
    ];
}

test('corregir sobrescribe lo capturado sin cambiar folio ni numero de inspeccion', function () {
    $concepto = Concepto::factory()->create();
    $usuario = usuarioQueCorrige();

    $this->actingAs($usuario)->post(route('admin.qal.inspecciones.store'), primeraParaCorregir($concepto));
    $inspeccion = Inspeccion::sole();

    $this->actingAs($usuario)
        ->put(route('admin.qal.inspecciones.update', $inspeccion), primeraParaCorregir($concepto, [
            'kg' => 120,
            'estatus' => 'liberado',
            'puntos' => ['p1_dim' => 'OK'],
        ]))
        ->assertRedirect(route('admin.qal.registros.index', ['ficha' => $inspeccion->id]))
        ->assertSessionHasNoErrors();

    $corregida = $inspeccion->fresh();

    expect(Inspeccion::count())->toBe(1)
        ->and($corregida->folio)->toBe($inspeccion->folio)
        ->and($corregida->numero_inspeccion)->toBe(1)
        ->and((float) $corregida->kg)->toBe(120.0)
        ->and($corregida->puntos()->count())->toBe(1);
});

test('al corregir no se cambia de pieza', function () {
    $concepto = Concepto::factory()->create();
    $usuario = usuarioQueCorrige();

    $this->actingAs($usuario)->post(route('admin.qal.inspecciones.store'), primeraParaCorregir($concepto));

    $this->actingAs($usuario)
        ->put(route('admin.qal.inspecciones.update', Inspeccion::sole()), primeraParaCorregir($concepto, ['consecutivo' => 2]))
        ->assertSessionHasErrors('fase');
});

test('capturar no alcanza para corregir ni para borrar', function () {
    $inspeccion = Inspeccion::factory()->create();
    $usuario = usuarioQueCorrige(['qal.inspecciones.crear']);

    $this->actingAs($usuario)->get(route('admin.qal.inspecciones.edit', $inspeccion))->assertForbidden();
    $this->actingAs($usuario)->delete(route('admin.qal.inspecciones.destroy', $inspeccion))->assertForbidden();

    expect(Inspeccion::count())->toBe(1);
});

test('editar abre la captura con lo capturado y reinspeccionar sólo con la pieza', function () {
    $concepto = Concepto::factory()->create(['marca' => 'PJ-CM1-10']);
    $usuario = usuarioQueCorrige();
    $this->actingAs($usuario)->post(route('admin.qal.inspecciones.store'), primeraParaCorregir($concepto, ['consecutivo' => 3]));
    $inspeccion = Inspeccion::sole();

    $this->actingAs($usuario)
        ->get(route('admin.qal.inspecciones.edit', $inspeccion))
        ->assertInertia(fn ($page) => $page
            ->component('admin/calidad/formularios/index')
            ->where('precarga.modo', 'editar')
            ->where('precarga.destino.metodo', 'put')
            ->where('precarga.campos.status', 'Rechazado')
            ->where('precarga.campos.p1_defl', 'Fuera de tol.'));

    $this->actingAs($usuario)
        ->get(route('admin.qal.inspecciones.reinspeccionar', $inspeccion))
        ->assertInertia(fn ($page) => $page
            ->where('precarga.modo', 'reinspeccionar')
            ->where('precarga.campos.consec', '3')
            ->where('precarga.marcaTexto', 'PJ-CM1-10')
            ->missing('precarga.campos.status')
            ->missing('precarga.campos.p1_defl'));
});

test('borrar se lleva la evidencia de adherencia del disco', function () {
    Storage::fake('public');
    $pieza = Pieza::factory()->create();
    $usuario = usuarioQueCorrige(['qal.inspecciones.crear', 'qal.inspecciones.eliminar']);

    $this->actingAs($usuario)->post(route('admin.qal.inspecciones.store'), pinturaConEvidencia($pieza, [
        'fotos' => [UploadedFile::fake()->create('tira.jpg', 100, 'image/jpeg')],
    ]))->assertSessionHasNoErrors();

    $ruta = Media::sole()->path;
    Storage::disk('public')->assertExists($ruta);

    $this->actingAs($usuario)
        ->delete(route('admin.qal.inspecciones.destroy', Inspeccion::sole()))
        ->assertRedirect(route('admin.qal.registros.index'));

    expect(Inspeccion::count())->toBe(0)->and(Media::count())->toBe(0);
    Storage::disk('public')->assertMissing($ruta);
});

test('corregir retira la foto marcada y conserva las demas', function () {
    Storage::fake('public');
    $pieza = Pieza::factory()->create();
    $usuario = usuarioQueCorrige();

    $this->actingAs($usuario)->post(route('admin.qal.inspecciones.store'), pinturaConEvidencia($pieza, [
        'fotos' => [
            UploadedFile::fake()->create('tira-1.jpg', 100, 'image/jpeg'),
            UploadedFile::fake()->create('tira-2.jpg', 100, 'image/jpeg'),
        ],
    ]))->assertSessionHasNoErrors();

    [$retirada, $conservada] = Media::query()->orderBy('id')->get()->all();

    $this->actingAs($usuario)
        ->put(route('admin.qal.inspecciones.update', Inspeccion::sole()), pinturaConEvidencia($pieza, [
            'fotos_quitar' => [$retirada->id],
        ]))
        ->assertSessionHasNoErrors();

    expect(Media::query()->pluck('id')->all())->toBe([$conservada->id]);
    Storage::disk('public')->assertMissing($retirada->path);
    Storage::disk('public')->assertExists($conservada->path);
});
