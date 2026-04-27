<?php

use App\Enums\Costos\DocumentoTipo;
use App\Models\Costos\Devolucion;
use App\Models\Costos\EntregaDetalle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'costos.devoluciones.ver',
        'costos.devoluciones.crear',
        'costos.devoluciones.cancelar',
    ] as $permName) {
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'costos.devoluciones.ver',
        'costos.devoluciones.crear',
        'costos.devoluciones.cancelar',
    ]);
});

test('crea una devolucion sobre una partida recibida con folio DV-', function () {
    $entregaDetalle = EntregaDetalle::factory()->create(['cantidad_recibida' => 20]);

    $this->actingAs($this->user)
        ->post('/admin/costos/devoluciones', [
            'entrega_detalle_id' => $entregaDetalle->id,
            'cantidad' => 5,
            'motivo' => 'Material defectuoso encontrado en revisión',
            'fecha' => '2026-04-26',
        ])
        ->assertRedirect();

    $devolucion = Devolucion::first();
    expect($devolucion)->not->toBeNull();
    expect($devolucion->folio)->toStartWith('DV-');
    expect((float) $devolucion->cantidad)->toBe(5.0);
    expect($devolucion->estatus->value)->toBe('vigente');
});

test('cantidad_devuelta y cantidad_neta_recibida reflejan devoluciones vigentes', function () {
    $ed = EntregaDetalle::factory()->create(['cantidad_recibida' => 20]);
    Devolucion::factory()->create(['entrega_detalle_id' => $ed->id, 'cantidad' => 3]);
    Devolucion::factory()->create(['entrega_detalle_id' => $ed->id, 'cantidad' => 2]);
    Devolucion::factory()->cancelada()->create(['entrega_detalle_id' => $ed->id, 'cantidad' => 100]);

    $ed->refresh();
    expect((float) $ed->cantidad_devuelta)->toBe(5.0);
    expect((float) $ed->cantidad_neta_recibida)->toBe(15.0);
});

test('rechaza devolucion que excede el saldo no devuelto', function () {
    $ed = EntregaDetalle::factory()->create(['cantidad_recibida' => 10]);
    Devolucion::factory()->create(['entrega_detalle_id' => $ed->id, 'cantidad' => 7]);

    $this->actingAs($this->user)
        ->post('/admin/costos/devoluciones', [
            'entrega_detalle_id' => $ed->id,
            'cantidad' => 5, // 7 + 5 = 12 > 10
            'motivo' => 'Otra devolucion no cabe',
            'fecha' => '2026-04-26',
        ])
        ->assertSessionHasErrors(['cantidad']);
});

test('rechaza devolucion con cantidad cero o negativa', function () {
    $ed = EntregaDetalle::factory()->create(['cantidad_recibida' => 10]);

    $this->actingAs($this->user)
        ->post('/admin/costos/devoluciones', [
            'entrega_detalle_id' => $ed->id,
            'cantidad' => 0,
            'motivo' => 'No deberia pasar',
            'fecha' => '2026-04-26',
        ])
        ->assertSessionHasErrors(['cantidad']);
});

test('rechaza devolucion sin motivo claro', function () {
    $ed = EntregaDetalle::factory()->create(['cantidad_recibida' => 10]);

    $this->actingAs($this->user)
        ->post('/admin/costos/devoluciones', [
            'entrega_detalle_id' => $ed->id,
            'cantidad' => 1,
            'motivo' => 'meh',
            'fecha' => '2026-04-26',
        ])
        ->assertSessionHasErrors(['motivo']);
});

test('cancelar devolucion la quita del calculo de cantidad neta', function () {
    $ed = EntregaDetalle::factory()->create(['cantidad_recibida' => 10]);
    $dev = Devolucion::factory()->create([
        'entrega_detalle_id' => $ed->id,
        'cantidad' => 4,
    ]);

    expect((float) $ed->fresh()->cantidad_neta_recibida)->toBe(6.0);

    $this->actingAs($this->user)
        ->post("/admin/costos/devoluciones/{$dev->id}/cancelar", [
            'motivo' => 'Acordamos con el proveedor mantener el material',
        ])
        ->assertRedirect();

    $dev->refresh();
    expect($dev->estatus->value)->toBe('cancelada');
    expect($dev->motivo_cancelacion)->toBe('Acordamos con el proveedor mantener el material');
    expect((float) $ed->fresh()->cantidad_neta_recibida)->toBe(10.0);
});

test('sube evidencia con la devolucion', function () {
    Storage::fake('public');
    $ed = EntregaDetalle::factory()->create(['cantidad_recibida' => 10]);

    $this->actingAs($this->user)
        ->post('/admin/costos/devoluciones', [
            'entrega_detalle_id' => $ed->id,
            'cantidad' => 2,
            'motivo' => 'Producto dañado en transporte',
            'fecha' => '2026-04-26',
            'evidencia' => UploadedFile::fake()->image('dañado.jpg'),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('media', [
        'mediable_type' => Devolucion::class,
        'descripcion' => DocumentoTipo::EvidenciaDevolucion->value,
    ]);
});

test('lista devoluciones con filtros', function () {
    Devolucion::factory()->count(2)->create();
    Devolucion::factory()->cancelada()->create();

    $this->actingAs($this->user)
        ->get('/admin/costos/devoluciones?estatus=vigente')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/costos/devoluciones/index')
            ->has('devoluciones.data', 2)
        );
});
