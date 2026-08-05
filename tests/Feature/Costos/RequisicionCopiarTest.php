<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\UsoCfdi;
use App\Models\Departamento;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'costos.requisiciones.ver',
        'costos.requisiciones.crear',
        'costos.requisiciones.ver-todas',
    ] as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.requisiciones.ver', 'costos.requisiciones.crear']);

    $this->depto = Departamento::factory()->create();
    $this->usoCfdi = UsoCfdi::factory()->create(['activo' => true]);
});

/** Requisición con partidas, de quien se indique. */
function requisicionCopiable(User $solicitante, Departamento $depto, UsoCfdi $uso, int $partidas = 2): Requisicion
{
    $obraRubro = ObraRubro::factory()->create();

    $requisicion = Requisicion::factory()->create([
        'solicitante_id' => $solicitante->id,
        'departamento_id' => $depto->id,
        'presupuesto_id' => $obraRubro->presupuesto_id,
        'justificacion' => 'Material de obra',
    ]);

    for ($i = 1; $i <= $partidas; $i++) {
        RequisicionDetalle::factory()->create([
            'requisicion_id' => $requisicion->id,
            'obra_rubro_id' => $obraRubro->id,
            'uso_cfdi_id' => $uso->id,
            'descripcion' => "Partida {$i}",
            'unidad' => 'pza',
            'cantidad' => $i * 5,
        ]);
    }

    return $requisicion;
}

test('lista las requisiciones propias para copiar', function () {
    $mia = requisicionCopiable($this->user, $this->depto, $this->usoCfdi);

    $respuesta = $this->actingAs($this->user)
        ->getJson('/admin/costos/requisiciones/copiables')
        ->assertOk();

    $folios = collect($respuesta->json('requisiciones'))->pluck('folio');

    expect($folios)->toContain($mia->folio)
        ->and($respuesta->json('requisiciones.0.partidas'))->toBe(2);
});

test('no lista las requisiciones de otro usuario', function () {
    $ajena = requisicionCopiable(User::factory()->create(), $this->depto, $this->usoCfdi);

    $respuesta = $this->actingAs($this->user)
        ->getJson('/admin/costos/requisiciones/copiables')
        ->assertOk();

    expect(collect($respuesta->json('requisiciones'))->pluck('folio'))
        ->not->toContain($ajena->folio);
});

test('quien puede ver todas si alcanza las ajenas', function () {
    $this->user->givePermissionTo('costos.requisiciones.ver-todas');
    $ajena = requisicionCopiable(User::factory()->create(), $this->depto, $this->usoCfdi);

    $respuesta = $this->actingAs($this->user)
        ->getJson('/admin/costos/requisiciones/copiables')
        ->assertOk();

    expect(collect($respuesta->json('requisiciones'))->pluck('folio'))
        ->toContain($ajena->folio);
});

test('la busqueda filtra por folio', function () {
    $mia = requisicionCopiable($this->user, $this->depto, $this->usoCfdi);
    requisicionCopiable($this->user, $this->depto, $this->usoCfdi);

    $respuesta = $this->actingAs($this->user)
        ->getJson('/admin/costos/requisiciones/copiables?search='.$mia->folio)
        ->assertOk();

    expect($respuesta->json('requisiciones'))->toHaveCount(1)
        ->and($respuesta->json('requisiciones.0.folio'))->toBe($mia->folio);
});

test('devuelve cabecera y partidas listas para el formulario', function () {
    $mia = requisicionCopiable($this->user, $this->depto, $this->usoCfdi);
    $primera = $mia->detalles()->orderBy('id')->first();

    $respuesta = $this->actingAs($this->user)
        ->getJson("/admin/costos/requisiciones/{$mia->id}/para-copiar")
        ->assertOk();

    expect($respuesta->json('folio'))->toBe($mia->folio)
        ->and($respuesta->json('cabecera.departamento_id'))->toBe($this->depto->id)
        ->and($respuesta->json('cabecera.presupuesto_id'))->toBe($mia->presupuesto_id)
        ->and($respuesta->json('cabecera.justificacion'))->toBe('Material de obra')
        ->and($respuesta->json('detalles'))->toHaveCount(2)
        ->and($respuesta->json('detalles.0.descripcion'))->toBe($primera->descripcion)
        // json_encode devuelve 5, no 5.0: se compara ya casteado.
        ->and((float) $respuesta->json('detalles.0.cantidad'))->toBe(5.0)
        ->and($respuesta->json('detalles.0.obra_rubro_id'))->toBe($primera->obra_rubro_id)
        // El presupuesto por partida sale de su centro de costos: el formulario
        // multipresupuesto lo necesita por renglón.
        ->and($respuesta->json('detalles.0.presupuesto_id'))->toBe($mia->presupuesto_id);
});

test('no se puede copiar una requisicion que no se puede abrir', function () {
    $ajena = requisicionCopiable(User::factory()->create(), $this->depto, $this->usoCfdi);

    $this->actingAs($this->user)
        ->getJson("/admin/costos/requisiciones/{$ajena->id}/para-copiar")
        ->assertForbidden();
});

test('sin permiso de crear no se puede copiar', function () {
    $sinPermiso = User::factory()->create();
    $sinPermiso->givePermissionTo('costos.requisiciones.ver');
    $mia = requisicionCopiable($sinPermiso, $this->depto, $this->usoCfdi);

    $this->actingAs($sinPermiso)
        ->getJson('/admin/costos/requisiciones/copiables')
        ->assertForbidden();

    $this->actingAs($sinPermiso)
        ->getJson("/admin/costos/requisiciones/{$mia->id}/para-copiar")
        ->assertForbidden();
});

test('la ruta copiables no la captura el parametro del resource', function () {
    // `requisiciones/copiables` va antes que `requisiciones/{requisicion}`: si se
    // invirtiera, "copiables" se leería como id y esto daría 404.
    $this->actingAs($this->user)
        ->getJson('/admin/costos/requisiciones/copiables')
        ->assertOk()
        ->assertJsonStructure(['requisiciones']);
});
