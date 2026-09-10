<?php

use App\Models\Cob\IcsoeSbcAnio;
use App\Models\Cob\IcsoeSeguimiento;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();

    foreach (['cob.icsoe-sbc.ver', 'cob.icsoe-sbc.editar'] as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $this->user->givePermissionTo(['cob.icsoe-sbc.ver', 'cob.icsoe-sbc.editar']);
});

it('renderiza el catálogo con los años sembrados por la migración', function () {
    $this->actingAs($this->user)
        ->get(route('admin.cob.icsoe-sbc.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/cob/icsoe-sbc/index')
            ->has('anios', 4)
        );
});

it('agrega un año nuevo', function () {
    $this->actingAs($this->user)
        ->post(route('admin.cob.icsoe-sbc.store'), [
            'anio' => 2027,
            'sbc' => 310.50,
            'costo_m2' => 1300,
            'prima_riesgo' => 6.25,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('cob_icsoe_sbc_anios', ['anio' => 2027, 'sbc' => 310.50]);
});

it('rechaza un año repetido', function () {
    $this->actingAs($this->user)
        ->post(route('admin.cob.icsoe-sbc.store'), [
            'anio' => 2026,
            'sbc' => 300,
            'costo_m2' => 1154,
            'prima_riesgo' => 7.58875,
        ])
        ->assertSessionHasErrors('anio');
});

it('edita un año existente', function () {
    $anio = IcsoeSbcAnio::where('anio', 2026)->firstOrFail();

    $this->actingAs($this->user)
        ->put(route('admin.cob.icsoe-sbc.update', $anio), [
            'anio' => 2026,
            'sbc' => 295.75,
            'costo_m2' => 1200,
            'prima_riesgo' => 5.5,
        ])
        ->assertRedirect();

    expect((float) $anio->fresh()->sbc)->toBe(295.75);
});

it('rechaza a quien no puede editar', function () {
    $otro = User::factory()->create();
    $otro->givePermissionTo('cob.icsoe-sbc.ver');

    $this->actingAs($otro)
        ->post(route('admin.cob.icsoe-sbc.store'), [
            'anio' => 2030,
            'sbc' => 300,
            'costo_m2' => 1154,
            'prima_riesgo' => 7.58875,
        ])
        ->assertForbidden();
});

it('cambiar el catálogo no altera los meses ya calculados', function () {
    $proyecto = App\Models\Proyecto::factory()->create();
    $seguimiento = app(App\Services\Cob\IcsoeService::class)->crear($proyecto, [
        'metodo' => 'superficie',
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => '2026-01-31',
        'superficie_m2' => 100,
        'costo_m2' => 1154,
        'prima_riesgo' => 7.58875,
    ]);

    expect((float) $seguimiento->meses()->first()->sbc)->toBe(290.00);

    IcsoeSbcAnio::where('anio', 2026)->update(['sbc' => 999]);

    expect((float) $seguimiento->meses()->first()->sbc)->toBe(290.00);

    // Hasta que alguien pide el recálculo.
    app(App\Services\Cob\IcsoeService::class)->recalcular($seguimiento, detectarCambio: false);

    expect((float) $seguimiento->meses()->first()->sbc)->toBe(999.00);
});

it('borrar un año no toca los seguimientos que ya lo usaron', function () {
    $seguimiento = IcsoeSeguimiento::factory()->create();
    $anio = IcsoeSbcAnio::where('anio', 2023)->firstOrFail();

    $this->actingAs($this->user)
        ->delete(route('admin.cob.icsoe-sbc.destroy', $anio))
        ->assertRedirect();

    $this->assertDatabaseMissing('cob_icsoe_sbc_anios', ['anio' => 2023]);
    expect($seguimiento->fresh())->not->toBeNull();
});
