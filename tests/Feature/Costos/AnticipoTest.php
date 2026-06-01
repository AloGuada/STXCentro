<?php

use App\Models\Costos\Anticipo;
use App\Models\Costos\AnticipoAplicacion;
use App\Models\Costos\Factura;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'costos.anticipos.ver',
        'costos.anticipos.crear',
        'costos.anticipos.aplicar',
        'costos.anticipos.cancelar',
    ] as $permName) {
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'costos.anticipos.ver',
        'costos.anticipos.crear',
        'costos.anticipos.aplicar',
        'costos.anticipos.cancelar',
    ]);
});

test('crea un anticipo con folio AN- y saldo igual al monto', function () {
    $proveedor = Proveedor::factory()->create();

    $this->actingAs($this->user)
        ->post('/admin/costos/anticipos', [
            'proveedor_id' => $proveedor->id,
            'monto' => 50000,
            'moneda' => 'mxn',
            'fecha' => '2026-04-26',
            'referencia' => 'TRF-001',
        ])
        ->assertRedirect();

    $anticipo = Anticipo::first();
    expect($anticipo)->not->toBeNull();
    expect($anticipo->folio)->toStartWith('AN-');
    expect((float) $anticipo->monto)->toBe(50000.0);
    expect((float) $anticipo->saldo_disponible)->toBe(50000.0);
    expect($anticipo->estatus->value)->toBe('vigente');
});

test('aplica anticipo a factura del mismo proveedor decrementa saldo', function () {
    $proveedor = Proveedor::factory()->create();
    $anticipo = Anticipo::factory()->create([
        'proveedor_id' => $proveedor->id,
        'monto' => 10000,
        'saldo_disponible' => 10000,
        'moneda' => 'mxn',
    ]);
    $factura = Factura::factory()->create([
        'proveedor_id' => $proveedor->id,
        'total' => 8000,
        'moneda' => 'mxn',
    ]);

    $this->actingAs($this->user)
        ->post('/admin/costos/anticipos/aplicar', [
            'anticipo_id' => $anticipo->id,
            'factura_id' => $factura->id,
            'monto' => 3000,
        ])
        ->assertRedirect();

    $anticipo->refresh();
    expect((float) $anticipo->saldo_disponible)->toBe(7000.0);
    expect($anticipo->estatus->value)->toBe('vigente');
    expect(AnticipoAplicacion::count())->toBe(1);
});

test('cuando el saldo llega a cero el anticipo pasa a agotado', function () {
    $proveedor = Proveedor::factory()->create();
    $anticipo = Anticipo::factory()->create([
        'proveedor_id' => $proveedor->id,
        'monto' => 5000,
        'saldo_disponible' => 5000,
        'moneda' => 'mxn',
    ]);
    $factura = Factura::factory()->create([
        'proveedor_id' => $proveedor->id,
        'total' => 5000,
        'moneda' => 'mxn',
    ]);

    $this->actingAs($this->user)
        ->post('/admin/costos/anticipos/aplicar', [
            'anticipo_id' => $anticipo->id,
            'factura_id' => $factura->id,
            'monto' => 5000,
        ])
        ->assertRedirect();

    $anticipo->refresh();
    expect((float) $anticipo->saldo_disponible)->toBe(0.0);
    expect($anticipo->estatus->value)->toBe('agotado');
});

test('rechaza aplicacion si excede saldo disponible del anticipo', function () {
    $proveedor = Proveedor::factory()->create();
    $anticipo = Anticipo::factory()->create([
        'proveedor_id' => $proveedor->id,
        'monto' => 1000,
        'saldo_disponible' => 1000,
        'moneda' => 'mxn',
    ]);
    $factura = Factura::factory()->create([
        'proveedor_id' => $proveedor->id,
        'total' => 5000,
        'moneda' => 'mxn',
    ]);

    $this->actingAs($this->user)
        ->post('/admin/costos/anticipos/aplicar', [
            'anticipo_id' => $anticipo->id,
            'factura_id' => $factura->id,
            'monto' => 1500,
        ])
        ->assertSessionHasErrors(['monto']);
});

test('rechaza aplicacion si excede el saldo pendiente de la factura', function () {
    $proveedor = Proveedor::factory()->create();
    $anticipo = Anticipo::factory()->create([
        'proveedor_id' => $proveedor->id,
        'monto' => 10000,
        'saldo_disponible' => 10000,
        'moneda' => 'mxn',
    ]);
    $factura = Factura::factory()->create([
        'proveedor_id' => $proveedor->id,
        'total' => 2000,
        'moneda' => 'mxn',
    ]);
    AnticipoAplicacion::factory()->create([
        'anticipo_id' => $anticipo->id,
        'factura_id' => $factura->id,
        'monto' => 1500,
    ]);

    $this->actingAs($this->user)
        ->post('/admin/costos/anticipos/aplicar', [
            'anticipo_id' => $anticipo->id,
            'factura_id' => $factura->id,
            'monto' => 1000, // total - aplicado(1500) = 500 disponible
        ])
        ->assertSessionHasErrors(['monto']);
});

test('rechaza aplicacion si los proveedores no coinciden', function () {
    $anticipo = Anticipo::factory()->create([
        'proveedor_id' => Proveedor::factory()->create()->id,
        'moneda' => 'mxn',
    ]);
    $factura = Factura::factory()->create([
        'proveedor_id' => Proveedor::factory()->create()->id,
        'moneda' => 'mxn',
    ]);

    $this->actingAs($this->user)
        ->post('/admin/costos/anticipos/aplicar', [
            'anticipo_id' => $anticipo->id,
            'factura_id' => $factura->id,
            'monto' => 100,
        ])
        ->assertSessionHasErrors(['factura_id']);
});

test('rechaza aplicacion si el anticipo no esta vigente', function () {
    $proveedor = Proveedor::factory()->create();
    $anticipo = Anticipo::factory()->cancelado()->create([
        'proveedor_id' => $proveedor->id,
        'moneda' => 'mxn',
    ]);
    $factura = Factura::factory()->create([
        'proveedor_id' => $proveedor->id,
        'moneda' => 'mxn',
    ]);

    $this->actingAs($this->user)
        ->post('/admin/costos/anticipos/aplicar', [
            'anticipo_id' => $anticipo->id,
            'factura_id' => $factura->id,
            'monto' => 100,
        ])
        ->assertSessionHasErrors(['anticipo_id']);
});

test('cancela anticipo solo si no tiene aplicaciones', function () {
    $proveedor = Proveedor::factory()->create();
    $anticipo = Anticipo::factory()->create([
        'proveedor_id' => $proveedor->id,
        'monto' => 1000,
        'saldo_disponible' => 1000,
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/anticipos/{$anticipo->id}/cancelar", [
            'motivo' => 'Anticipo no procedente, recuperar fondos',
        ])
        ->assertRedirect();

    expect($anticipo->fresh()->estatus->value)->toBe('cancelado');
});

test('no cancela anticipo con aplicaciones registradas', function () {
    $proveedor = Proveedor::factory()->create();
    $anticipo = Anticipo::factory()->create(['proveedor_id' => $proveedor->id]);
    AnticipoAplicacion::factory()->create(['anticipo_id' => $anticipo->id]);

    $this->actingAs($this->user)
        ->post("/admin/costos/anticipos/{$anticipo->id}/cancelar", [
            'motivo' => 'Cualquier motivo de mas de diez caracteres',
        ])
        ->assertSessionHasErrors(['estatus']);
});

test('endpoint de anticipos disponibles para factura filtra por proveedor moneda y vigentes con saldo', function () {
    $proveedorA = Proveedor::factory()->create();
    $proveedorB = Proveedor::factory()->create();
    $factura = Factura::factory()->create(['proveedor_id' => $proveedorA->id, 'moneda' => 'mxn']);

    $vigenteSameProv = Anticipo::factory()->create(['proveedor_id' => $proveedorA->id, 'moneda' => 'mxn', 'saldo_disponible' => 5000]);
    $otroProv = Anticipo::factory()->create(['proveedor_id' => $proveedorB->id, 'moneda' => 'mxn']);
    $otraMoneda = Anticipo::factory()->create(['proveedor_id' => $proveedorA->id, 'moneda' => 'usd']);
    $agotado = Anticipo::factory()->agotado()->create(['proveedor_id' => $proveedorA->id, 'moneda' => 'mxn']);

    $response = $this->actingAs($this->user)
        ->getJson("/admin/costos/facturas/{$factura->id}/anticipos-disponibles");

    $response->assertOk();
    $ids = collect($response->json('anticipos'))->pluck('id')->all();
    expect($ids)->toContain($vigenteSameProv->id);
    expect($ids)->not->toContain($otroProv->id);
    expect($ids)->not->toContain($otraMoneda->id);
    expect($ids)->not->toContain($agotado->id);
});
