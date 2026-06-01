<?php

use App\Models\Costos\Factura;
use App\Models\Costos\NotaCredito;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'costos.notas-credito.ver',
        'costos.notas-credito.cancelar',
    ] as $permName) {
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'costos.notas-credito.ver',
        'costos.notas-credito.cancelar',
    ]);
});

test('cancelar nota deja de afectar saldo de la factura', function () {
    $factura = Factura::factory()->create(['total' => 10000]);
    $nota = NotaCredito::factory()->create([
        'factura_id' => $factura->id,
        'monto' => 3000,
    ]);

    expect((float) $factura->fresh()->monto_notas_credito)->toBe(3000.0);

    $this->actingAs($this->user)
        ->post("/admin/costos/notas-credito/{$nota->id}/cancelar", [
            'motivo' => 'El proveedor cancelo la nota fiscal',
        ])
        ->assertRedirect();

    $nota->refresh();
    expect($nota->estatus->value)->toBe('cancelada');
    expect($nota->motivo_cancelacion)->toBe('El proveedor cancelo la nota fiscal');

    expect((float) $factura->fresh()->monto_notas_credito)->toBe(0.0);
    expect((float) $factura->fresh()->saldo_facturado)->toBe(10000.0);
});

test('lista notas con filtros por factura y estatus', function () {
    $facturaA = Factura::factory()->create();
    $facturaB = Factura::factory()->create();
    NotaCredito::factory()->create(['factura_id' => $facturaA->id]);
    NotaCredito::factory()->create(['factura_id' => $facturaA->id]);
    NotaCredito::factory()->cancelada()->create(['factura_id' => $facturaB->id]);

    $this->actingAs($this->user)
        ->get('/admin/costos/notas-credito?factura_id='.$facturaA->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/costos/notas-credito/index')
            ->has('notas.data', 2)
        );
});

test('admin no puede crear nota de crédito (solo el proveedor desde el portal)', function () {
    $factura = Factura::factory()->create(['total' => 10000]);

    $this->actingAs($this->user)
        ->post('/admin/costos/notas-credito', [
            'factura_id' => $factura->id,
            'monto' => 1500,
            'concepto' => 'Descuento',
            'fecha_emision' => '2026-04-26',
        ])
        ->assertStatus(405);
});
