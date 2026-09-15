<?php

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Movimiento;
use App\Models\Costos\Producto;
use App\Models\User;
use App\Services\Alm\AlmacenLedger;
use App\Support\HoraLocal;
use Spatie\Permission\Models\Permission;

/**
 * La base guarda en UTC y las pantallas de Almacén enseñan en la zona de
 * presentación. Un asiento capturado a las 19:30 de Mérida se guarda a la
 * 01:30 del día siguiente en UTC; el kardex lo tiene que enseñar el día que
 * fue, no el de mañana.
 */
it('el kardex enseña la hora local, no la de la base', function (): void {
    config(['app.display_timezone' => 'America/Merida']);

    $almacen = Almacen::factory()->create();
    $producto = Producto::factory()->create();

    $movimiento = app(AlmacenLedger::class)->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 5, 10);
    Movimiento::query()->whereKey($movimiento->id)->update(['created_at' => '2026-09-16 01:30:00']);

    $user = User::factory()->create();
    $permisos = ['alm.kardex.ver', 'alm.existencias.ver', 'alm.almacenes.ver-todos'];
    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }
    $user->givePermissionTo($permisos);

    $this->actingAs($user)
        ->get(route('admin.alm.kardex.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('movimientos.data.0.fecha', '2026-09-15 19:30:00'));

    // Y el filtro por día también es local: el 15 lo incluye, el 16 no.
    $this->actingAs($user)
        ->get(route('admin.alm.kardex.index', ['desde' => '2026-09-15', 'hasta' => '2026-09-15']))
        ->assertInertia(fn ($page) => $page->has('movimientos.data', 1));

    $this->actingAs($user)
        ->get(route('admin.alm.kardex.index', ['desde' => '2026-09-16']))
        ->assertInertia(fn ($page) => $page->has('movimientos.data', 0));
});

it('HoraLocal convierte y tolera nulos', function (): void {
    config(['app.display_timezone' => 'America/Merida']);

    expect(HoraLocal::texto(now()->parse('2026-09-16 01:30:00', 'UTC')))->toBe('2026-09-15 19:30:00')
        ->and(HoraLocal::texto(null))->toBeNull();
});
