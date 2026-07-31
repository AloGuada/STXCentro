<?php

use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.requisiciones.ver', 'costos.requisiciones.ver-todas'] as $permName) {
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.requisiciones.ver', 'costos.requisiciones.ver-todas']);

    $this->deJuan = Requisicion::factory()->create([
        'solicitante_id' => User::factory()->create(['name' => 'Juan Pérez'])->id,
    ]);
    $this->deJuan->forceFill(['folio' => 'REQ-AB-001'])->save();

    $this->deMaria = Requisicion::factory()->create([
        'solicitante_id' => User::factory()->create(['name' => 'María López'])->id,
    ]);

    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $this->deMaria->id]);
    RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => Proveedor::factory()->create(['razon_social' => 'ACEROS DEL NORTE SA'])->id,
    ]);
});

function idsBuscados(string $search): array
{
    return collect(
        test()->actingAs(test()->user)
            ->get(route('admin.costos.requisiciones.index', ['search' => $search]))
            ->assertOk()
            ->viewData('page')['props']['requisiciones']['data']
    )->pluck('id')->all();
}

test('busca por folio sin importar mayúsculas', function () {
    expect(idsBuscados('req-ab'))->toBe([$this->deJuan->id]);
});

test('busca por nombre del solicitante sin importar mayúsculas', function () {
    expect(idsBuscados('juan'))->toBe([$this->deJuan->id]);
    expect(idsBuscados('JUAN'))->toBe([$this->deJuan->id]);
    expect(idsBuscados('lópez'))->toBe([$this->deMaria->id]);
});

test('busca por razón social del proveedor que cotizó, sin importar mayúsculas', function () {
    expect(idsBuscados('aceros'))->toBe([$this->deMaria->id]);
});

test('sin coincidencias no devuelve requisiciones', function () {
    expect(idsBuscados('zzzz'))->toBe([]);
});
