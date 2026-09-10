<?php

use App\Enums\Qal\AmbitoDefecto;
use App\Enums\Qal\VeredictoLote;
use App\Models\Obra;
use App\Models\Qal\Defecto;
use App\Models\Qal\LoteAccesorio;
use App\Models\Qal\Sublote;
use App\Models\Qal\SubloteDefecto;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Lotes de accesorios: cientos de unidades iguales que llegan por entregas y
 * se liberan por muestreo.
 *
 * Las reglas que se comprueban: lote y sublote nacen juntos (RN-15), la muestra
 * nunca pasa de lo que trajo la entrega, una reinspección no pisa a la original
 * (RN-14), y el avance cuenta cada sublote una vez, con su última inspección.
 */
function usuarioDeAccesorios(array $permisos = ['qal.accesorios.crear']): User
{
    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

/**
 * 300 unidades a nivel II: muestra de 50, acepta con 10, rechaza con 11.
 *
 * @return array<string, mixed>
 */
function entregaValida(Obra $obra, array $cambios = []): array
{
    return [
        'obra_id' => $obra->id,
        'fecha' => now()->toDateString(),
        'marca' => ' acc-pl12 ',
        'descripcion' => 'Placa de conexión',
        'total_unidades' => 1600,
        'kg_unitario' => 4.5,
        'elementos_unitarios' => 2,
        'unidades' => 300,
        'nivel' => 'II',
        'conformes' => 50,
        'rechazadas' => [],
        ...$cambios,
    ];
}

test('la entrega crea el lote y su sublote juntos, con la marca normalizada', function () {
    $obra = Obra::factory()->create();

    $this->actingAs(usuarioDeAccesorios())
        ->post(route('admin.qal.accesorios.sublotes.store'), entregaValida($obra))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $sublote = Sublote::sole();

    expect(LoteAccesorio::sole()->only(['marca', 'total_unidades']))->toBe(['marca' => 'ACC-PL12', 'total_unidades' => 1600])
        ->and($sublote->only(['unidades', 'muestra', 'aceptacion', 'rechazo', 'numero_inspeccion']))
        ->toBe(['unidades' => 300, 'muestra' => 50, 'aceptacion' => 10, 'rechazo' => 11, 'numero_inspeccion' => 1])
        ->and($sublote->veredicto)->toBe(VeredictoLote::Aceptado)
        ->and($sublote->sublote_origen_id)->toBeNull();
});

test('otra entrega de la misma marca cuelga del mismo lote y actualiza sus datos', function () {
    $obra = Obra::factory()->create();
    $usuario = usuarioDeAccesorios();

    $this->actingAs($usuario)->post(route('admin.qal.accesorios.sublotes.store'), entregaValida($obra));
    $this->actingAs($usuario)->post(route('admin.qal.accesorios.sublotes.store'), entregaValida($obra, ['total_unidades' => 1700]));

    expect(LoteAccesorio::sole()->total_unidades)->toBe(1700)
        ->and(Sublote::count())->toBe(2);
});

test('la muestra nunca pasa de las unidades que trajo la entrega', function () {
    $obra = Obra::factory()->create();
    $usuario = usuarioDeAccesorios();

    // Una unidad a nivel II: la tabla pide 2, la entrega sólo trae 1.
    $this->actingAs($usuario)
        ->post(route('admin.qal.accesorios.sublotes.store'), entregaValida($obra, ['unidades' => 1, 'conformes' => 2]))
        ->assertSessionHasErrors('conformes');

    $this->actingAs($usuario)
        ->post(route('admin.qal.accesorios.sublotes.store'), entregaValida($obra, ['unidades' => 1, 'conformes' => 1]))
        ->assertSessionHasNoErrors();

    expect(Sublote::sole()->muestra)->toBe(1);
});

test('cada unidad rechazada lleva sus defectos y las rechazadas deciden el veredicto', function () {
    $obra = Obra::factory()->create();
    $soldadura = Defecto::factory()->deAmbito(AmbitoDefecto::Soldadura)->create();
    $barrenos = Defecto::factory()->deAmbito(AmbitoDefecto::AccesorioBarrenos)->create();

    // 20 unidades a nivel II: muestra de 5, rechaza con 2.
    $this->actingAs(usuarioDeAccesorios())
        ->post(route('admin.qal.accesorios.sublotes.store'), entregaValida($obra, [
            'unidades' => 20,
            'conformes' => 3,
            'rechazadas' => [
                ['defectos' => [$soldadura->id, $barrenos->id]],
                ['defectos' => [$barrenos->id]],
            ],
            'disposicion' => 'Se separaron solo las piezas malas',
        ]))
        ->assertSessionHasNoErrors();

    $sublote = Sublote::sole();

    expect($sublote->rechazadas)->toBe(2)
        ->and($sublote->veredicto)->toBe(VeredictoLote::Rechazado)
        ->and($sublote->disposicion)->toBe('Se separaron solo las piezas malas')
        ->and(SubloteDefecto::query()->where('unidad', 1)->count())->toBe(2)
        ->and(SubloteDefecto::count())->toBe(3);
});

test('una unidad rechazada sin defecto, o con uno de pintura, no se guarda', function () {
    $obra = Obra::factory()->create();
    $pintura = Defecto::factory()->deAmbito(AmbitoDefecto::Pintura)->create();
    $usuario = usuarioDeAccesorios();

    $this->actingAs($usuario)
        ->post(route('admin.qal.accesorios.sublotes.store'), entregaValida($obra, ['conformes' => 10, 'rechazadas' => [['defectos' => []]]]))
        ->assertSessionHasErrors('rechazadas.0.defectos');

    $this->actingAs($usuario)
        ->post(route('admin.qal.accesorios.sublotes.store'), entregaValida($obra, ['conformes' => 10, 'rechazadas' => [['defectos' => [$pintura->id]]]]))
        ->assertSessionHasErrors('rechazadas.0.defectos.0');

    expect(Sublote::count())->toBe(0)->and(LoteAccesorio::count())->toBe(0);
});

test('la reinspeccion cuelga de la primera inspeccion y sube el numero', function () {
    $original = Sublote::factory()->rechazado()->create();
    $lote = $original->lote;
    $usuario = usuarioDeAccesorios();
    $datos = fn (Sublote $origen) => entregaValida($lote->obra, [
        'marca' => $lote->marca,
        'total_unidades' => $lote->total_unidades,
        'unidades' => 100,
        'conformes' => 14,
        'rechazadas' => array_fill(0, 6, ['defectos' => [Defecto::factory()->deAmbito(AmbitoDefecto::AccesorioLimpieza)->create()->id]]),
        'sublote_origen_id' => $origen->id,
    ]);

    $this->actingAs($usuario)->post(route('admin.qal.accesorios.sublotes.store'), $datos($original))->assertSessionHasNoErrors();
    $segunda = Sublote::query()->latest('id')->first();

    // Reinspeccionar la segunda sigue colgando de la primera: el grupo es uno.
    $this->actingAs($usuario)->post(route('admin.qal.accesorios.sublotes.store'), $datos($segunda))->assertSessionHasNoErrors();
    $tercera = Sublote::query()->latest('id')->first();

    expect($segunda->only(['sublote_origen_id', 'numero_inspeccion']))->toBe(['sublote_origen_id' => $original->id, 'numero_inspeccion' => 2])
        ->and($tercera->only(['sublote_origen_id', 'numero_inspeccion']))->toBe(['sublote_origen_id' => $original->id, 'numero_inspeccion' => 3])
        ->and($original->fresh()->veredicto)->toBe(VeredictoLote::Rechazado);
});

test('no se reinspecciona un sublote liberado ni uno de otra marca', function () {
    $aceptado = Sublote::factory()->create();
    $rechazado = Sublote::factory()->rechazado()->create();
    $usuario = usuarioDeAccesorios();
    $lote = $aceptado->lote;

    $this->actingAs($usuario)
        ->post(route('admin.qal.accesorios.sublotes.store'), entregaValida($lote->obra, [
            'marca' => $lote->marca, 'unidades' => 100, 'conformes' => 20, 'sublote_origen_id' => $aceptado->id,
        ]))
        ->assertSessionHasErrors('sublote_origen_id');

    $this->actingAs($usuario)
        ->post(route('admin.qal.accesorios.sublotes.store'), entregaValida($lote->obra, [
            'marca' => $lote->marca, 'unidades' => 100, 'conformes' => 20, 'sublote_origen_id' => $rechazado->id,
        ]))
        ->assertSessionHasErrors('sublote_origen_id');
});

test('editar sobrescribe la inspeccion del sublote sin crear otra', function () {
    $obra = Obra::factory()->create();
    $usuario = usuarioDeAccesorios(['qal.accesorios.crear', 'qal.accesorios.editar']);
    $this->actingAs($usuario)->post(route('admin.qal.accesorios.sublotes.store'), entregaValida($obra, ['conformes' => 20]));
    $sublote = Sublote::sole();

    expect($sublote->veredicto)->toBeNull();

    $this->actingAs($usuario)
        ->put(route('admin.qal.accesorios.sublotes.update', $sublote), entregaValida($obra, ['conformes' => 50]))
        ->assertSessionHasNoErrors();

    expect(Sublote::count())->toBe(1)
        ->and($sublote->fresh()->veredicto)->toBe(VeredictoLote::Aceptado)
        ->and($sublote->fresh()->numero_inspeccion)->toBe(1);
});

test('la primera inspeccion no se borra mientras tenga reinspecciones', function () {
    $original = Sublote::factory()->rechazado()->create();
    $reinspeccion = Sublote::factory()->reinspeccionDe($original)->create();
    $usuario = usuarioDeAccesorios(['qal.accesorios.eliminar']);

    $this->actingAs($usuario)
        ->delete(route('admin.qal.accesorios.sublotes.destroy', $original))
        ->assertSessionHasErrors('sublote');

    expect(Sublote::count())->toBe(2);

    $this->actingAs($usuario)
        ->delete(route('admin.qal.accesorios.sublotes.destroy', $reinspeccion))
        ->assertSessionHasNoErrors();

    expect(Sublote::count())->toBe(1);
});

test('el avance cuenta cada sublote una vez y la concesion libera', function () {
    $lote = LoteAccesorio::factory()->create(['total_unidades' => 500]);
    $reparado = Sublote::factory()->rechazado()->create(['lote_id' => $lote->id, 'unidades' => 100]);
    Sublote::factory()->reinspeccionDe($reparado)->create();
    Sublote::factory()->rechazado('Liberado bajo concesión')->create(['lote_id' => $lote->id, 'unidades' => 50]);
    Sublote::factory()->rechazado()->create(['lote_id' => $lote->id, 'unidades' => 30]);

    $this->actingAs(usuarioDeAccesorios(['qal.accesorios.ver']))
        ->get(route('admin.qal.accesorios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/calidad/accesorios/index')
            ->where('lotes.0.avance.recibidas', 180)
            ->where('lotes.0.avance.liberadas', 150)
            ->where('lotes.0.avance.detenidas', 30)
            ->where('lotes.0.avance.sublotes', 3)
            ->where('lotes.0.avance.sin_disposicion', 1)
            ->has('lotes.0.grupos', 3)
            ->has('lotes.0.grupos.0', 2));
});

test('reinspeccionar abre la captura en modo lote con el muestreo en blanco', function () {
    $original = Sublote::factory()->rechazado()->create();

    $this->actingAs(usuarioDeAccesorios())
        ->get(route('admin.qal.accesorios.sublotes.reinspeccionar', $original))
        ->assertInertia(fn ($page) => $page
            ->component('admin/calidad/formularios/index')
            ->where('precarga.modoCaptura', 'acc')
            ->where('precarga.acc.origenId', $original->id)
            ->where('precarga.acc.numero', 2)
            ->where('precarga.acc.conformes', 0)
            ->where('precarga.campos.ac_marca', $original->lote->marca)
            ->has('lotes', 1));
});
