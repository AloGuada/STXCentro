<?php

use App\Enums\Qal\EstatusInspeccion;
use App\Models\Prod\Pieza;
use App\Models\Qal\Adherencia;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Pintura;
use App\Models\User;
use Database\Seeders\QalPuntosInspeccionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

/**
 * Inspección de pintura: espesores por SSPC-PA2 y la prueba de adherencia con
 * su evidencia.
 */
beforeEach(fn () => $this->seed(QalPuntosInspeccionSeeder::class));

function inspectorDePintura(): User
{
    Permission::firstOrCreate(['name' => 'qal.inspecciones.crear', 'guard_name' => 'web']);

    $usuario = User::factory()->create();
    $usuario->givePermissionTo('qal.inspecciones.crear');

    return $usuario;
}

/**
 * Cinco mediciones a la vista y una sexta oculta que no debe contar.
 *
 * @return array<string, mixed>
 */
function pinturaValida(Pieza $pieza, array $cambios = []): array
{
    return [
        'obra_id' => $pieza->catalogo->obra_id,
        'fase' => '3ª',
        'fecha' => now()->toDateString(),
        'prod_pieza_id' => $pieza->id,
        'kg' => 300,
        'estatus' => 'liberado',
        'puntos' => ['p3_esp' => 'OK', 'p3_vis' => 'OK'],
        'pintura' => [
            'espesor_requerido_mils' => 3.5,
            'metodo' => 'SSPC-PA2 (calibre magnético)',
            'mediciones_visibles' => 5,
            'lecturas' => [[2, 2, 2], [4, 4, 4], [3, 4, 5], [5, null, null], [null, null, null], [10, 10, 10]],
        ],
        ...$cambios,
    ];
}

test('el espesor es el promedio de promedios y una medicion baja no rechaza', function () {
    $pieza = Pieza::factory()->create();

    $this->actingAs(inspectorDePintura())
        ->post(route('admin.qal.inspecciones.store'), pinturaValida($pieza))
        ->assertSessionHasNoErrors();

    $pintura = Pintura::sole();

    expect((float) $pintura->promedio_mils)->toBe(3.75)
        ->and($pintura->cumple)->toBeTrue()
        ->and($pintura->mediciones_bajas)->toBe([1])
        ->and($pintura->lecturas()->count())->toBe(10)
        ->and($pintura->lecturas()->where('medicion', 6)->exists())->toBeFalse()
        ->and(Inspeccion::sole()->estatus)->toBe(EstatusInspeccion::Liberado);
});

test('no cumple cuando el promedio queda bajo el requerido', function () {
    $pieza = Pieza::factory()->create();
    $datos = pinturaValida($pieza);
    $datos['pintura']['espesor_requerido_mils'] = 4;

    $this->actingAs(inspectorDePintura())
        ->post(route('admin.qal.inspecciones.store'), $datos)
        ->assertSessionHasNoErrors();

    expect(Pintura::sole()->cumple)->toBeFalse();
});

test('la adherencia guarda sus tres tiras y la evidencia', function () {
    Storage::fake('public');
    $pieza = Pieza::factory()->create();

    $this->actingAs(inspectorDePintura())
        ->post(route('admin.qal.inspecciones.store'), pinturaValida($pieza, [
            'adherencia' => ['metodo' => 'A', 'resultado' => 'Aceptado', 'tiras' => ['5A', '4A', '5A']],
            'fotos' => [
                UploadedFile::fake()->create('tira-1.jpg', 200, 'image/jpeg'),
                UploadedFile::fake()->create('tiras.pdf', 300, 'application/pdf'),
            ],
        ]))
        ->assertSessionHasNoErrors();

    $adherencia = Adherencia::sole();

    expect($adherencia->tiras->pluck('clasificacion')->all())->toBe(['5A', '4A', '5A'])
        ->and($adherencia->fotos)->toHaveCount(2);

    Storage::disk('public')->assertExists($adherencia->fotos->pluck('path')->all());
});

test('cada tira es del metodo elegido', function () {
    $pieza = Pieza::factory()->create();

    $this->actingAs(inspectorDePintura())
        ->post(route('admin.qal.inspecciones.store'), pinturaValida($pieza, [
            'adherencia' => ['metodo' => 'A', 'tiras' => ['5B']],
        ]))
        ->assertSessionHasErrors('adherencia.tiras.0');
});

test('sin prueba de adherencia no hay evidencia que guardar', function () {
    Storage::fake('public');
    $pieza = Pieza::factory()->create();

    $this->actingAs(inspectorDePintura())
        ->post(route('admin.qal.inspecciones.store'), pinturaValida($pieza, [
            'fotos' => [UploadedFile::fake()->create('tira-1.jpg', 200, 'image/jpeg')],
        ]))
        ->assertSessionHasErrors('fotos');
});

test('el muestreo de lote no es de pintura', function () {
    $pieza = Pieza::factory()->create();

    $this->actingAs(inspectorDePintura())
        ->post(route('admin.qal.inspecciones.store'), pinturaValida($pieza, [
            'muestreo' => ['tamano_lote' => 10, 'nivel' => 'II', 'conformes' => 1, 'rechazadas' => 0],
        ]))
        ->assertSessionHasErrors('muestreo');
});
