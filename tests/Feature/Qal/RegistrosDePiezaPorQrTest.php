<?php

use App\Enums\Qal\AmbitoPunto;
use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\PuntoInspeccion;
use App\Models\User;
use Database\Seeders\QalPuntosInspeccionSeeder;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * De Producción a Registros: una pieza con su QR en el catálogo de la obra, sus
 * inspecciones capturadas por el formulario y lo que Registros enseña de ellas.
 *
 * Las pruebas de cada fase revisan lo que queda en la base, y las de Registros
 * parten de inspecciones hechas a mano; ésta junta las dos mitades, que es el
 * recorrido que hace el inspector.
 */
beforeEach(fn () => $this->seed(QalPuntosInspeccionSeeder::class));

function inspectorQueAudita(): User
{
    foreach (['qal.inspecciones.crear', 'qal.registros.ver'] as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo(['qal.inspecciones.crear', 'qal.registros.ver']);

    return $usuario;
}

/**
 * Lo que manda el formulario al guardar una pieza escaneada por su QR.
 *
 * @return array<string, mixed>
 */
function capturaPorQr(Pieza $pieza, string $fase, array $cambios = []): array
{
    return [
        'obra_id' => $pieza->catalogo->obra_id,
        'fase' => $fase,
        'fecha' => now()->toDateString(),
        'prod_pieza_id' => $pieza->id,
        'kg' => 850,
        'estatus' => 'liberado',
        'puntos' => [],
        ...$cambios,
    ];
}

/**
 * 2ª en soldado con sus juntas del mapeo.
 *
 * @param  list<array<string, mixed>>  $juntas
 * @return array<string, mixed>
 */
function soldadoPorQr(Pieza $pieza, array $juntas, string $estatus = 'liberado'): array
{
    return capturaPorQr($pieza, '2ª', [
        'subetapa' => 'soldado',
        'estatus' => $estatus,
        'puntos' => ['p2_elem' => '11', 'p2_long' => 'OK', 'p2_placas' => 'n/a'],
        'juntas' => $juntas,
    ]);
}

/**
 * Una junta del mapeo con sus puntos en OK, o con el primero en defecto.
 *
 * @return array<string, mixed>
 */
function juntaDeLaPieza(string $identificador, bool $conDefecto = false): array
{
    $puntos = PuntoInspeccion::query()
        ->where('ambito', AmbitoPunto::Junta->value)
        ->orderBy('orden')
        ->pluck('clave')
        ->mapWithKeys(fn (string $clave): array => [$clave => 'OK'])
        ->all();

    if ($conDefecto) {
        $puntos[array_key_first($puntos)] = 'Defecto';
    }

    return ['identificador' => $identificador, 'tipo' => 'ranura', 'puntos' => $puntos];
}

/**
 * Las props de Registros tal como llegan a la pantalla.
 *
 * @return array<string, mixed>
 */
function registrosQueVe(TestCase $prueba, User $usuario, array $filtros = []): array
{
    $props = [];

    $prueba->actingAs($usuario)
        ->get(route('admin.qal.registros.index', $filtros))
        ->assertOk()
        ->assertInertia(function ($page) use (&$props): void {
            $props = $page->toArray()['props'];
        });

    return $props;
}

test('las inspecciones capturadas por el QR de la pieza salen en registros', function () {
    $pieza = Pieza::factory()->create();
    // Otra pieza, de otra obra: lo que los filtros tienen que dejar fuera.
    $ajena = Pieza::factory()->create();
    $usuario = inspectorQueAudita();

    $capturas = [
        capturaPorQr($pieza, '2ª', ['subetapa' => 'armado_vestido', 'estatus' => 'pendiente']),
        soldadoPorQr($pieza, [juntaDeLaPieza('J1'), juntaDeLaPieza('J2')]),
        capturaPorQr($pieza, '3ª', [
            'kg' => 300,
            'puntos' => ['p3_esp' => 'OK', 'p3_vis' => 'OK'],
            'pintura' => [
                'espesor_requerido_mils' => 3.5,
                'metodo' => 'SSPC-PA2 (calibre magnético)',
                'mediciones_visibles' => 5,
                'lecturas' => [[4, 4, 4], [4, 4, 4], [4, 4, 4], [4, 4, 4], [4, 4, 4]],
            ],
        ]),
        capturaPorQr($ajena, '2ª', ['subetapa' => 'armado_vestido', 'estatus' => 'pendiente']),
    ];

    foreach ($capturas as $captura) {
        $this->actingAs($usuario)
            ->post(route('admin.qal.inspecciones.store'), $captura)
            ->assertSessionHasNoErrors();
    }

    // Buscar el QR trae las tres inspecciones de la pieza, y son una sola pieza.
    $porQr = registrosQueVe($this, $usuario, ['buscar' => $pieza->qr]);
    $filas = collect($porQr['registros']['data']);

    expect($filas)->toHaveCount(3)
        ->and($filas->pluck('qr')->unique()->values()->all())->toBe([$pieza->qr])
        ->and($filas->pluck('marca')->unique()->values()->all())->toBe([$pieza->marca->marca])
        ->and($filas->pluck('fase')->sort()->values()->all())->toBe(['2ª', '2ª', '3ª'])
        ->and($porQr['conteo'])->toMatchArray(['registros' => 3, 'piezas' => 1]);

    // Filtrar por la obra de Producción deja fuera la pieza de la otra obra.
    $porObra = collect(registrosQueVe($this, $usuario, ['obra' => $pieza->catalogo->obra_id])['registros']['data']);

    expect($porObra)->toHaveCount(3)
        ->and($porObra->pluck('qr'))->not->toContain($ajena->qr);

    // Y la fase acota dentro de la misma pieza.
    expect(registrosQueVe($this, $usuario, ['buscar' => $pieza->qr, 'fase' => '3ª'])['registros']['data'])->toHaveCount(1);

    // La ficha del soldado trae el QR, sus juntas y la historia entera de la pieza.
    $soldado = Inspeccion::query()->where('qr', $pieza->qr)->where('subetapa', 'soldado')->sole();
    $ficha = registrosQueVe($this, $usuario, ['ficha' => $soldado->id])['ficha'];

    expect(collect($ficha['cabecera'])->firstWhere('etiqueta', 'QR')['valor'])->toBe($pieza->qr)
        ->and(collect($ficha['juntas'])->pluck('identificador')->all())->toBe(['J1', 'J2'])
        ->and($ficha['historial'])->toHaveCount(3);
});

test('reinspeccionar la pieza por su QR es un registro nuevo y el anterior se conserva', function () {
    $pieza = Pieza::factory()->create();
    $usuario = inspectorQueAudita();

    // Se rechaza por una junta con defecto, se repara y se vuelve a revisar.
    $this->actingAs($usuario)
        ->post(route('admin.qal.inspecciones.store'), soldadoPorQr($pieza, [juntaDeLaPieza('J1', conDefecto: true)], 'rechazado'))
        ->assertSessionHasNoErrors();
    $this->actingAs($usuario)
        ->post(route('admin.qal.inspecciones.store'), soldadoPorQr($pieza, [juntaDeLaPieza('J1')]))
        ->assertSessionHasNoErrors();

    $props = registrosQueVe($this, $usuario, ['buscar' => $pieza->qr]);
    $filas = collect($props['registros']['data'])->sortBy('numero_inspeccion')->values();

    expect($filas->pluck('numero_inspeccion')->all())->toBe([1, 2])
        ->and($filas->pluck('estatus')->all())->toBe(['rechazado', 'liberado'])
        ->and($props['conteo'])->toMatchArray(['registros' => 2, 'piezas' => 1]);

    $primera = registrosQueVe($this, $usuario, ['ficha' => $filas[0]['id']])['ficha'];
    $segunda = registrosQueVe($this, $usuario, ['ficha' => $filas[1]['id']])['ficha'];

    // La primera sigue ahí con su rechazo, pero ya no se reinspecciona: manda la última.
    expect($primera['juntas'][0])->toMatchArray(['identificador' => 'J1', 'intento' => 1, 'resultado' => 'con_defecto'])
        ->and($primera['puedeReinspeccionar'])->toBeFalse()
        ->and($primera['historial'])->toHaveCount(2)
        ->and($segunda['juntas'][0])->toMatchArray(['identificador' => 'J1', 'intento' => 2, 'resultado' => 'correcta'])
        ->and($segunda['puedeReinspeccionar'])->toBeFalse();
});
