<?php

use App\Enums\Cob\IcsoeEstatus;
use App\Models\Cob\IcsoeMes;
use App\Models\Cob\IcsoeSeguimiento;
use App\Models\Cob\Partida;
use App\Models\Obra;
use App\Models\Proyecto;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function usuarioIcsoe(array $permisos = ['cob.icsoe.ver', 'cob.icsoe.crear', 'cob.icsoe.editar', 'cob.icsoe.eliminar', 'cob.icsoe.verificar']): User
{
    $user = User::factory()->create();

    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($permisos);

    return $user;
}

/** Proyecto con una obra a precio alzado y una partida: valor a ejecutar conocido. */
function proyectoConValor(float $monto = 1000000): Proyecto
{
    $proyecto = Proyecto::factory()->create();
    $obra = Obra::factory()->create([
        'proyecto_id' => $proyecto->id,
        'tipo' => 'base',
        'tipo_contrato' => 'precio_alzado',
    ]);
    Partida::factory()->create(['obra_id' => $obra->id, 'monto' => $monto]);

    return $proyecto;
}

beforeEach(function () {
    $this->user = usuarioIcsoe();
});

describe('index', function () {
    it('renderiza la tabla', function () {
        IcsoeSeguimiento::factory()->count(3)->create();

        $this->actingAs($this->user)
            ->get(route('admin.cob.icsoe.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cob/icsoe/index')
                ->has('seguimientos', 3)
            );
    });

    it('pone los pendientes de verificación hasta arriba', function () {
        $vigente = IcsoeSeguimiento::factory()->create();
        $pendiente = IcsoeSeguimiento::factory()->pendiente()->create();

        $this->actingAs($this->user)
            ->get(route('admin.cob.icsoe.index'))
            ->assertInertia(fn ($page) => $page
                ->where('seguimientos.0.id', $pendiente->id)
                ->where('seguimientos.1.id', $vigente->id)
            );
    });

    it('filtra por estatus', function () {
        IcsoeSeguimiento::factory()->count(2)->create();
        IcsoeSeguimiento::factory()->pendiente()->create();

        $this->actingAs($this->user)
            ->get(route('admin.cob.icsoe.index', ['estatus' => 'pendiente_verificacion']))
            ->assertInertia(fn ($page) => $page->has('seguimientos', 1));
    });

    it('rechaza al usuario sin permiso', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.cob.icsoe.index'))
            ->assertForbidden();
    });
});

describe('alta', function () {
    it('crea el seguimiento y genera los meses del periodo', function () {
        $proyecto = proyectoConValor(15870818);

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.icsoe.store', $proyecto), [
                'metodo' => 'porcentaje',
                'fecha_inicio' => '2023-02-06',
                'fecha_fin' => '2023-05-05',
                'porcentaje_mo' => 30,
                'prima_riesgo' => 7.58875,
            ])
            ->assertRedirect();

        $seguimiento = IcsoeSeguimiento::firstOrFail();

        expect($seguimiento->total_dias)->toBe(89);
        expect((float) $seguimiento->monto_base)->toBe(15870818.0);
        expect((float) $seguimiento->mo_estimada_total)->toBe(4761245.40);
        expect($seguimiento->estatus)->toBe(IcsoeEstatus::Vigente);
        expect($seguimiento->meses)->toHaveCount(4);
        expect($seguimiento->meses->pluck('dias_proyecto')->all())->toBe([23, 31, 30, 5]);
    });

    it('calcula por superficie sin usar el valor a ejecutar', function () {
        $proyecto = proyectoConValor(999999);

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.icsoe.store', $proyecto), [
                'metodo' => 'superficie',
                'fecha_inicio' => '2023-02-06',
                'fecha_fin' => '2023-05-05',
                'superficie_m2' => 1164,
                'costo_m2' => 1154,
                'prima_riesgo' => 7.58875,
            ])
            ->assertRedirect();

        expect((float) IcsoeSeguimiento::firstOrFail()->mo_estimada_total)->toBe(1343256.0);
    });

    it('rechaza un segundo seguimiento del mismo proyecto', function () {
        $proyecto = proyectoConValor();
        IcsoeSeguimiento::factory()->create(['proyecto_id' => $proyecto->id]);

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.icsoe.store', $proyecto), [
                'metodo' => 'porcentaje',
                'fecha_inicio' => '2026-01-01',
                'fecha_fin' => '2026-03-31',
                'porcentaje_mo' => 30,
                'prima_riesgo' => 7.58875,
            ])
            ->assertStatus(409);
    });

    it('exige superficie cuando el método es superficie', function () {
        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.icsoe.store', proyectoConValor()), [
                'metodo' => 'superficie',
                'fecha_inicio' => '2026-01-01',
                'fecha_fin' => '2026-03-31',
                'prima_riesgo' => 7.58875,
            ])
            ->assertSessionHasErrors(['superficie_m2', 'costo_m2']);
    });

    it('rechaza un periodo invertido', function () {
        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.icsoe.store', proyectoConValor()), [
                'metodo' => 'porcentaje',
                'fecha_inicio' => '2026-03-31',
                'fecha_fin' => '2026-01-01',
                'porcentaje_mo' => 30,
                'prima_riesgo' => 7.58875,
            ])
            ->assertSessionHasErrors('fecha_fin');
    });
});

describe('edición del periodo', function () {
    beforeEach(function () {
        $this->proyecto = proyectoConValor(1000000);

        $this->actingAs($this->user)->post(route('admin.cob.proyectos.icsoe.store', $this->proyecto), [
            'metodo' => 'porcentaje',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-04-30',
            'porcentaje_mo' => 30,
            'prima_riesgo' => 7.58875,
        ]);

        $this->seguimiento = IcsoeSeguimiento::firstOrFail();
    });

    it('conserva los días cotizados al mover las fechas', function () {
        $marzo = $this->seguimiento->meses()->where('mes', 3)->firstOrFail();
        $marzo->update(['dias_cotizados' => 40, 'mo_real' => 11000]);

        $this->actingAs($this->user)
            ->put(route('admin.cob.icsoe.update', $this->seguimiento), [
                'metodo' => 'porcentaje',
                'fecha_inicio' => '2026-01-01',
                'fecha_fin' => '2026-06-30',
                'porcentaje_mo' => 30,
                'prima_riesgo' => 7.58875,
            ])
            ->assertRedirect();

        expect($this->seguimiento->fresh()->meses)->toHaveCount(6);
        expect((float) $marzo->fresh()->dias_cotizados)->toBe(40.0);
    });

    it('borra el mes vacío que queda fuera y conserva el que tiene captura', function () {
        $marzo = $this->seguimiento->meses()->where('mes', 3)->firstOrFail();
        $marzo->update(['dias_cotizados' => 12]);

        $this->actingAs($this->user)
            ->put(route('admin.cob.icsoe.update', $this->seguimiento), [
                'metodo' => 'porcentaje',
                'fecha_inicio' => '2026-01-01',
                'fecha_fin' => '2026-02-28',
                'porcentaje_mo' => 30,
                'prima_riesgo' => 7.58875,
            ]);

        expect($marzo->fresh()->fuera_de_rango)->toBeTrue();
        expect((float) $marzo->fresh()->dias_cotizados)->toBe(12.0);
        expect(IcsoeMes::where('seguimiento_id', $this->seguimiento->id)->where('mes', 4)->exists())->toBeFalse();
    });

    it('revive el mes fuera de rango si el periodo vuelve a cubrirlo', function () {
        $marzo = $this->seguimiento->meses()->where('mes', 3)->firstOrFail();
        $marzo->update(['dias_cotizados' => 12]);

        $payload = [
            'metodo' => 'porcentaje',
            'porcentaje_mo' => 30,
            'prima_riesgo' => 7.58875,
        ];

        $this->actingAs($this->user)->put(route('admin.cob.icsoe.update', $this->seguimiento), [
            ...$payload, 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-28',
        ]);
        $this->actingAs($this->user)->put(route('admin.cob.icsoe.update', $this->seguimiento), [
            ...$payload, 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-04-30',
        ]);

        expect($marzo->fresh()->fuera_de_rango)->toBeFalse();
        expect($marzo->fresh()->dias_proyecto)->toBe(31);
        expect((float) $marzo->fresh()->dias_cotizados)->toBe(12.0);
    });
});

describe('captura mensual', function () {
    beforeEach(function () {
        $this->proyecto = proyectoConValor(1000000);

        $this->actingAs($this->user)->post(route('admin.cob.proyectos.icsoe.store', $this->proyecto), [
            'metodo' => 'porcentaje',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-02-28',
            'porcentaje_mo' => 30,
            'prima_riesgo' => 7.58875,
        ]);

        $this->seguimiento = IcsoeSeguimiento::firstOrFail();
    });

    it('guarda el lote y recalcula los totales sin marcar pendiente', function () {
        $meses = $this->seguimiento->meses;

        $this->actingAs($this->user)
            ->put(route('admin.cob.icsoe.meses.update', $this->seguimiento), [
                'meses' => [
                    ['id' => $meses[0]->id, 'dias_cotizados' => 100, 'sbc_aplicado' => 290],
                    ['id' => $meses[1]->id, 'dias_cotizados' => 50, 'sbc_aplicado' => 290],
                ],
            ])
            ->assertRedirect();

        $seguimiento = $this->seguimiento->fresh();

        // 150 días × 290 = 43,500
        expect((float) $seguimiento->mo_real_total)->toBe(43500.0);
        expect((float) $seguimiento->diferencia_mo)->toBe(300000.0 - 43500.0);
        expect($seguimiento->estatus)->toBe(IcsoeEstatus::Vigente);
    });

    it('rechaza un mes de otro seguimiento', function () {
        $ajeno = IcsoeMes::factory()->create();

        $this->actingAs($this->user)
            ->put(route('admin.cob.icsoe.meses.update', $this->seguimiento), [
                'meses' => [['id' => $ajeno->id, 'dias_cotizados' => 10, 'sbc_aplicado' => 290]],
            ])
            ->assertSessionHasErrors('meses.0.id');
    });
});

describe('verificación', function () {
    it('limpia el diff y sella quién verificó', function () {
        $seguimiento = IcsoeSeguimiento::factory()->pendiente()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cob.icsoe.verificar', $seguimiento))
            ->assertRedirect();

        $seguimiento->refresh();

        expect($seguimiento->estatus)->toBe(IcsoeEstatus::Vigente);
        expect($seguimiento->monto_base_anterior)->toBeNull();
        expect($seguimiento->mo_estimada_total_anterior)->toBeNull();
        expect($seguimiento->motivo_cambio)->toBeNull();
        expect($seguimiento->verificado_por)->toBe($this->user->id);
        expect($seguimiento->verificado_at)->not->toBeNull();
    });

    it('rechaza a quien no tiene el permiso de verificar', function () {
        $seguimiento = IcsoeSeguimiento::factory()->pendiente()->create();

        $this->actingAs(usuarioIcsoe(['cob.icsoe.ver']))
            ->post(route('admin.cob.icsoe.verificar', $seguimiento))
            ->assertForbidden();
    });
});
