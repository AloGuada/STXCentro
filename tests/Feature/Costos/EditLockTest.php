<?php

use App\Models\Costos\SolicitudPago;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'costos.requisiciones.crear', 'guard_name' => 'web']);
    $this->userA = User::factory()->create();
    $this->userA->givePermissionTo('costos.requisiciones.crear');
    $this->userB = User::factory()->create();
    $this->userB->givePermissionTo('costos.requisiciones.crear');
    darPermisosSolicitudesPago($this->userA);
    darPermisosSolicitudesPago($this->userB);
});

describe('edit lock via endpoint /lock', function () {
    test('toma lock libre exitosamente', function () {
        $solicitud = SolicitudPago::factory()->create();

        $this->actingAs($this->userA)
            ->post("/admin/costos/lock/solicitud-pago/{$solicitud->id}")
            ->assertOk();

        $solicitud->refresh();
        expect($solicitud->locked_by)->toBe($this->userA->id);
        expect($solicitud->locked_at)->not->toBeNull();
    });

    test('segundo usuario recibe 423 si lock esta vigente', function () {
        $solicitud = SolicitudPago::factory()->create();

        $this->actingAs($this->userA)
            ->post("/admin/costos/lock/solicitud-pago/{$solicitud->id}")
            ->assertOk();

        $this->actingAs($this->userB)
            ->post("/admin/costos/lock/solicitud-pago/{$solicitud->id}")
            ->assertStatus(423)
            ->assertJsonStructure(['message', 'locked_by' => ['id', 'name']]);
    });

    test('lock expirado puede ser tomado por otro usuario', function () {
        $solicitud = SolicitudPago::factory()->create();

        $this->actingAs($this->userA)
            ->post("/admin/costos/lock/solicitud-pago/{$solicitud->id}")
            ->assertOk();

        // Simular TTL pasado (default 15 min)
        $solicitud->forceFill(['locked_at' => now()->subMinutes(20)])->save();

        $this->actingAs($this->userB)
            ->post("/admin/costos/lock/solicitud-pago/{$solicitud->id}")
            ->assertOk();

        expect($solicitud->fresh()->locked_by)->toBe($this->userB->id);
    });

    test('mismo usuario puede retomar/refrescar su propio lock', function () {
        $solicitud = SolicitudPago::factory()->create();

        $this->actingAs($this->userA)
            ->post("/admin/costos/lock/solicitud-pago/{$solicitud->id}")
            ->assertOk();

        $primera = $solicitud->fresh()->locked_at;
        Carbon::setTestNow(now()->addMinutes(3));

        $this->actingAs($this->userA)
            ->post("/admin/costos/lock/solicitud-pago/{$solicitud->id}")
            ->assertOk();

        expect($solicitud->fresh()->locked_at->gt($primera))->toBeTrue();
        Carbon::setTestNow();
    });

    test('unlock limpia locked_by y locked_at', function () {
        $solicitud = SolicitudPago::factory()->create();

        $this->actingAs($this->userA)
            ->post("/admin/costos/lock/solicitud-pago/{$solicitud->id}")
            ->assertOk();

        $this->actingAs($this->userA)
            ->post("/admin/costos/unlock/solicitud-pago/{$solicitud->id}")
            ->assertOk();

        $solicitud->refresh();
        expect($solicitud->locked_by)->toBeNull();
        expect($solicitud->locked_at)->toBeNull();
    });

    test('tipo de entidad invalido retorna 404', function () {
        $this->actingAs($this->userA)
            ->post('/admin/costos/lock/monstruo/99')
            ->assertNotFound();
    });
});

describe('optimistic lock via updated_at en update', function () {
    test('update con _version correcto procede', function () {
        $solicitud = SolicitudPago::factory()->create(['estatus' => 'borrador']);

        $response = $this->actingAs($this->userA)
            ->put("/admin/costos/solicitudes-pago/{$solicitud->id}", [
                'departamento_id' => $solicitud->departamento_id,
                'tipo_solicitud_id' => $solicitud->tipo_solicitud_id,
                'concepto' => 'Actualizado',
                'tipo_pago' => $solicitud->tipo_pago,
                'tipo_moneda' => $solicitud->tipo_moneda ?? 'mxn',
                'monto_total' => 100,
                'detalles' => [],
                '_version' => $solicitud->updated_at->toIso8601String(),
            ]);

        $response->assertRedirect();
        expect($solicitud->fresh()->concepto)->toBe('Actualizado');
    });

    test('update con _version obsoleto recibe 409', function () {
        $solicitud = SolicitudPago::factory()->create(['estatus' => 'borrador']);
        $versionVieja = $solicitud->updated_at->toIso8601String();

        // Alguien mas modifica (simulado) — updated_at cambia
        Carbon::setTestNow(now()->addMinutes(1));
        $solicitud->update(['concepto' => 'modificado por otro']);
        Carbon::setTestNow();

        $this->actingAs($this->userA)
            ->put("/admin/costos/solicitudes-pago/{$solicitud->id}", [
                'departamento_id' => $solicitud->departamento_id,
                'tipo_solicitud_id' => $solicitud->tipo_solicitud_id,
                'concepto' => 'mi intento',
                'tipo_pago' => $solicitud->tipo_pago,
                'tipo_moneda' => $solicitud->tipo_moneda ?? 'mxn',
                'monto_total' => 100,
                'detalles' => [],
                '_version' => $versionVieja,
            ])
            ->assertStatus(409);

        // El concepto NO se sobrescribio con 'mi intento'
        expect($solicitud->fresh()->concepto)->toBe('modificado por otro');
    });

    test('update libera el lock si estaba tomado', function () {
        $solicitud = SolicitudPago::factory()->create(['estatus' => 'borrador']);
        $solicitud->lock($this->userA->id);

        $this->actingAs($this->userA)
            ->put("/admin/costos/solicitudes-pago/{$solicitud->id}", [
                'departamento_id' => $solicitud->departamento_id,
                'tipo_solicitud_id' => $solicitud->tipo_solicitud_id,
                'concepto' => 'Actualizado',
                'tipo_pago' => $solicitud->tipo_pago,
                'tipo_moneda' => $solicitud->tipo_moneda ?? 'mxn',
                'monto_total' => 100,
                'detalles' => [],
                '_version' => $solicitud->fresh()->updated_at->toIso8601String(),
            ])
            ->assertRedirect();

        expect($solicitud->fresh()->locked_by)->toBeNull();
    });
});
