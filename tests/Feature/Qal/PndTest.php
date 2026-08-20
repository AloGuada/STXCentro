<?php

use App\Enums\Qal\MetodoPnd;
use App\Enums\Qal\ResultadoPnd;
use App\Models\Qal\Etapa;
use App\Models\Qal\Laboratorio;
use App\Models\Qal\Obra;
use App\Models\Qal\ObraPndPlan;
use App\Models\Qal\Pieza;
use App\Models\Qal\PndJunta;
use App\Models\Qal\PndReporte;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Pruebas no destructivas: el informe del laboratorio y el avance del contrato.
 *
 * Lo que se comprueba aquí no es que los campos se guarden —eso lo daría por
 * bueno cualquier CRUD— sino las cuatro reglas que hacen que el número final
 * signifique algo: el denominador es el spot, la marca del laboratorio se
 * conserva aunque la pieza no exista, la ausencia de plan no es un cero, y el
 * encabezado vive una sola vez.
 */
function usuarioPnd(array $permisos): User
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
function informeValido(Obra $obra, Laboratorio $laboratorio, array $juntas = []): array
{
    return [
        'reporte_no' => 'UT-1042',
        'metodo' => 'UT',
        'laboratorio_id' => $laboratorio->id,
        'qal_obra_id' => $obra->id,
        'lugar' => 'Planta 2',
        'fecha_prueba' => '2026-08-10',
        'fecha_emision' => '2026-08-12',
        'anio' => 2026,
        'semana' => 33,
        'porcentaje_inspeccion' => 10,
        'tecnico' => 'R. Muñoz',
        'material' => 'A572 Gr.50',
        'norma' => 'AWS D1.1',
        'parametros' => [
            ['clave' => 'Frecuencia', 'valor' => '2.25 MHz'],
        ],
        'juntas' => $juntas !== [] ? $juntas : [
            ['marca' => 'TP12-3', 'junta' => 'J-18-1-2', 'resultado' => 'aceptada'],
        ],
    ];
}

test('la pantalla abre con su permiso y queda cerrada sin el', function () {
    $this->actingAs(usuarioPnd(['qal.pnd.ver']))
        ->get(route('admin.qal.pnd.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/calidad/pnd/index'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.qal.pnd.index'))
        ->assertForbidden();
});

test('ver informes no alcanza para capturarlos', function () {
    $this->actingAs(usuarioPnd(['qal.pnd.ver']))
        ->get(route('admin.qal.pnd.create'))
        ->assertForbidden();
});

test('se captura el informe con su encabezado, sus parametros y su rejilla', function () {
    $obra = Obra::factory()->create();
    $laboratorio = Laboratorio::factory()->create();

    $this->actingAs(usuarioPnd(['qal.pnd.crear', 'qal.pnd.ver']))
        ->post(route('admin.qal.pnd.store'), informeValido($obra, $laboratorio))
        ->assertRedirect();

    $reporte = PndReporte::firstWhere('reporte_no', 'UT-1042');

    expect($reporte)->not->toBeNull()
        ->and($reporte->metodo)->toBe(MetodoPnd::Ut)
        ->and($reporte->juntas)->toHaveCount(1)
        ->and($reporte->parametros)->toHaveCount(1);
});

test('un informe sin puntos examinados no se guarda', function () {
    $obra = Obra::factory()->create();
    $laboratorio = Laboratorio::factory()->create();

    $datos = informeValido($obra, $laboratorio);
    $datos['juntas'] = [];

    $this->actingAs(usuarioPnd(['qal.pnd.crear']))
        ->post(route('admin.qal.pnd.store'), $datos)
        ->assertSessionHasErrors('juntas');

    expect(PndReporte::count())->toBe(0);
});

test('el folio del laboratorio no se repite', function () {
    $obra = Obra::factory()->create();
    $laboratorio = Laboratorio::factory()->create();
    PndReporte::factory()->create(['reporte_no' => 'UT-1042', 'qal_obra_id' => $obra->id]);

    $this->actingAs(usuarioPnd(['qal.pnd.crear']))
        ->post(route('admin.qal.pnd.store'), informeValido($obra, $laboratorio))
        ->assertSessionHasErrors('reporte_no');
});

/**
 * `J-18-1-2` es el **segundo punto** de la junta `18-1`. Contar juntas en lugar
 * de spots subestima el volumen ensayado y desvía el porcentaje de rechazo.
 */
test('el spot se deduce del ultimo segmento de la referencia', function () {
    $obra = Obra::factory()->create();
    $laboratorio = Laboratorio::factory()->create();

    $this->actingAs(usuarioPnd(['qal.pnd.crear', 'qal.pnd.ver']))
        ->post(route('admin.qal.pnd.store'), informeValido($obra, $laboratorio, [
            ['marca' => 'TP12-3', 'junta' => 'J-18-1-2', 'resultado' => 'aceptada'],
            ['marca' => 'TP12-3', 'junta' => 'J-18-1', 'resultado' => 'rechazada', 'discontinuidad' => 'Porosidad'],
        ]));

    $juntas = PndJunta::orderBy('id')->get();

    expect($juntas[0]->junta)->toBe('18-1')
        ->and($juntas[0]->spot)->toBe(2)
        // Con dos segmentos no se parte nada: `18-1` es el nombre de la junta,
        // no la junta 18 en su punto 1.
        ->and($juntas[1]->junta)->toBe('18-1')
        ->and($juntas[1]->spot)->toBe(1);
});

test('el spot tecleado gana sobre el deducido', function () {
    $obra = Obra::factory()->create();
    $laboratorio = Laboratorio::factory()->create();

    $this->actingAs(usuarioPnd(['qal.pnd.crear', 'qal.pnd.ver']))
        ->post(route('admin.qal.pnd.store'), informeValido($obra, $laboratorio, [
            ['marca' => 'TP12-3', 'junta' => 'J-18-1-2', 'spot' => 7, 'resultado' => 'aceptada'],
        ]));

    expect(PndJunta::first()->spot)->toBe(7);
});

/**
 * El laboratorio entrega antes de que Calidad dé de alta las piezas: exigir la
 * pieza para poder capturar el informe sería no poder capturarlo.
 */
test('la marca del laboratorio se conserva aunque la pieza no exista', function () {
    $obra = Obra::factory()->create();
    $laboratorio = Laboratorio::factory()->create();

    $this->actingAs(usuarioPnd(['qal.pnd.crear', 'qal.pnd.ver']))
        ->post(route('admin.qal.pnd.store'), informeValido($obra, $laboratorio));

    $junta = PndJunta::first();

    expect($junta->marca)->toBe('TP12-3')
        ->and($junta->qal_pieza_id)->toBeNull();
});

test('la junta se engancha a la pieza cuando la marca ya existe', function () {
    $obra = Obra::factory()->create();
    $etapa = Etapa::factory()->create(['obra_id' => $obra->id]);
    $pieza = Pieza::factory()->create(['etapa_id' => $etapa->id, 'marca' => 'TP12-3']);
    $laboratorio = Laboratorio::factory()->create();

    $this->actingAs(usuarioPnd(['qal.pnd.crear', 'qal.pnd.ver']))
        ->post(route('admin.qal.pnd.store'), informeValido($obra, $laboratorio));

    expect(PndJunta::first()->qal_pieza_id)->toBe($pieza->id);
});

test('las marcas sueltas se enganchan despues, cuando la pieza aparece', function () {
    $obra = Obra::factory()->create();
    $laboratorio = Laboratorio::factory()->create();
    $usuario = usuarioPnd(['qal.pnd.crear', 'qal.pnd.editar', 'qal.pnd.ver']);

    $this->actingAs($usuario)->post(route('admin.qal.pnd.store'), informeValido($obra, $laboratorio));

    $reporte = PndReporte::first();
    expect(PndJunta::first()->qal_pieza_id)->toBeNull();

    $etapa = Etapa::factory()->create(['obra_id' => $obra->id]);
    $pieza = Pieza::factory()->create(['etapa_id' => $etapa->id, 'marca' => 'TP12-3']);

    $this->actingAs($usuario)
        ->post(route('admin.qal.pnd.resolver-marcas', $reporte))
        ->assertRedirect();

    expect(PndJunta::first()->qal_pieza_id)->toBe($pieza->id);
});

test('la pieza de otra obra no engancha la marca', function () {
    $obra = Obra::factory()->create();
    $otra = Obra::factory()->create();
    $etapa = Etapa::factory()->create(['obra_id' => $otra->id]);
    Pieza::factory()->create(['etapa_id' => $etapa->id, 'marca' => 'TP12-3']);
    $laboratorio = Laboratorio::factory()->create();

    $this->actingAs(usuarioPnd(['qal.pnd.crear', 'qal.pnd.ver']))
        ->post(route('admin.qal.pnd.store'), informeValido($obra, $laboratorio));

    expect(PndJunta::first()->qal_pieza_id)->toBeNull();
});

test('un parametro sin valor se descarta en vez de tumbar el guardado', function () {
    $obra = Obra::factory()->create();
    $laboratorio = Laboratorio::factory()->create();

    $datos = informeValido($obra, $laboratorio);
    $datos['parametros'] = [
        ['clave' => 'Frecuencia', 'valor' => '2.25 MHz'],
        ['clave' => 'Palpador', 'valor' => ''],
        ['clave' => '', 'valor' => ''],
    ];

    $this->actingAs(usuarioPnd(['qal.pnd.crear', 'qal.pnd.ver']))
        ->post(route('admin.qal.pnd.store'), $datos)
        ->assertRedirect();

    expect(PndReporte::first()->parametros)->toHaveCount(1);
});

test('editar reemplaza la rejilla completa', function () {
    $obra = Obra::factory()->create();
    $laboratorio = Laboratorio::factory()->create();
    $usuario = usuarioPnd(['qal.pnd.crear', 'qal.pnd.editar', 'qal.pnd.ver']);

    $this->actingAs($usuario)->post(route('admin.qal.pnd.store'), informeValido($obra, $laboratorio, [
        ['marca' => 'TP12-3', 'junta' => 'J-18-1-1', 'resultado' => 'aceptada'],
        ['marca' => 'TP12-3', 'junta' => 'J-18-1-2', 'resultado' => 'aceptada'],
    ]));

    $reporte = PndReporte::first();

    $this->actingAs($usuario)->post(route('admin.qal.pnd.update', $reporte), informeValido($obra, $laboratorio, [
        ['marca' => 'TP12-3', 'junta' => 'J-18-1-1', 'resultado' => 'rechazada', 'discontinuidad' => 'Falta de fusión'],
    ]));

    $reporte->refresh();

    expect($reporte->juntas)->toHaveCount(1)
        ->and($reporte->juntas->first()->resultado)->toBe(ResultadoPnd::Rechazada);
});

/**
 * **Ausencia de fila = «no entra en este contrato». Un 0 = «se pactaron cero».**
 * El tablero los trata distinto, así que el nulo tiene que llegar a la pantalla
 * sin convertirse en 0 por el camino.
 */
test('el avance distingue el metodo fuera de contrato del pactado en cero', function () {
    $obra = Obra::factory()->create();
    ObraPndPlan::factory()->create(['qal_obra_id' => $obra->id, 'metodo' => MetodoPnd::Ut, 'comprometidas' => 40]);
    ObraPndPlan::factory()->create(['qal_obra_id' => $obra->id, 'metodo' => MetodoPnd::Mt, 'comprometidas' => 0]);

    $this->actingAs(usuarioPnd(['qal.pnd.ver']))
        ->get(route('admin.qal.pnd.index', ['obra' => $obra->id]))
        ->assertInertia(function ($page) {
            $plan = collect($page->toArray()['props']['plan'])->keyBy('metodo');

            expect($plan['UT']['comprometidas'])->toBe(40)
                ->and($plan['MT']['comprometidas'])->toBe(0)
                // RT no se pactó: no tiene fila, y eso no es un cero.
                ->and($plan['RT']['comprometidas'])->toBeNull();
        });
});

test('el avance cuenta puntos examinados, no juntas', function () {
    $obra = Obra::factory()->create();
    ObraPndPlan::factory()->create(['qal_obra_id' => $obra->id, 'metodo' => MetodoPnd::Ut, 'comprometidas' => 10]);

    $reporte = PndReporte::factory()->delMetodo(MetodoPnd::Ut)->create(['qal_obra_id' => $obra->id]);
    PndJunta::factory()->count(3)->create(['qal_pnd_reporte_id' => $reporte->id, 'junta' => '18-1']);
    PndJunta::factory()->rechazada()->create(['qal_pnd_reporte_id' => $reporte->id, 'junta' => '18-1']);

    $this->actingAs(usuarioPnd(['qal.pnd.ver']))
        ->get(route('admin.qal.pnd.index', ['obra' => $obra->id]))
        ->assertInertia(function ($page) {
            $ut = collect($page->toArray()['props']['plan'])->firstWhere('metodo', 'UT');

            // Cuatro renglones de la misma junta son cuatro puntos ensayados.
            expect($ut['spots'])->toBe(4)
                ->and($ut['rechazados'])->toBe(1);
        });
});

test('quitar un metodo del plan borra su fila en vez de dejarla en cero', function () {
    $obra = Obra::factory()->create();
    ObraPndPlan::factory()->create(['qal_obra_id' => $obra->id, 'metodo' => MetodoPnd::Ut, 'comprometidas' => 40]);

    $this->actingAs(usuarioPnd(['qal.obras.editar']))
        ->put(route('admin.qal.pnd.plan', $obra), [
            'nota' => '10% de las juntas de penetración completa, cláusula 7.3',
            'plan' => [
                ['metodo' => 'UT', 'pactado' => false, 'comprometidas' => 40],
                ['metodo' => 'MT', 'pactado' => true, 'comprometidas' => 12],
            ],
        ])
        ->assertRedirect();

    $obra->refresh();

    expect($obra->pndPlan()->where('metodo', MetodoPnd::Ut)->exists())->toBeFalse()
        ->and($obra->pndPlan()->where('metodo', MetodoPnd::Mt)->first()->comprometidas)->toBe(12)
        ->and($obra->pnd_nota)->toContain('cláusula 7.3');
});

/**
 * El plan es contrato, no captura: quien teclea informes no negocia cuántas
 * pruebas se pactaron.
 */
test('el plan comprometido no lo edita quien solo captura informes', function () {
    $obra = Obra::factory()->create();

    $this->actingAs(usuarioPnd(['qal.pnd.crear', 'qal.pnd.editar']))
        ->put(route('admin.qal.pnd.plan', $obra), ['nota' => null, 'plan' => []])
        ->assertForbidden();
});

test('borrar el informe se lleva su rejilla', function () {
    $obra = Obra::factory()->create();
    $reporte = PndReporte::factory()->create(['qal_obra_id' => $obra->id]);
    PndJunta::factory()->count(2)->create(['qal_pnd_reporte_id' => $reporte->id]);

    $this->actingAs(usuarioPnd(['qal.pnd.eliminar', 'qal.pnd.ver']))
        ->delete(route('admin.qal.pnd.destroy', $reporte))
        ->assertRedirect();

    expect(PndReporte::count())->toBe(0)
        ->and(PndJunta::count())->toBe(0);
});
