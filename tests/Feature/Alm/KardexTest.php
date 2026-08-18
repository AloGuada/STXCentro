<?php

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Ubicacion;
use App\Models\Costos\Producto;
use App\Models\User;
use App\Services\Alm\AlmacenLedger;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>  $permisos
 */
function usuarioDeKardex(array $permisos = ['alm.kardex.ver', 'alm.existencias.ver']): User
{
    $user = User::factory()->create();

    $nombres = [...$permisos, 'alm.almacenes.ver-todos'];

    foreach ($nombres as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($nombres);

    return $user;
}

describe('kardex', function () {
    it('lista los movimientos del mas nuevo al mas viejo', function () {
        $almacen = Almacen::factory()->create(['clave' => 'AG']);
        $producto = Producto::factory()->create(['codigo' => 'TOR-0012']);
        $ledger = app(AlmacenLedger::class);

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 10);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Salida, -30);

        $this->actingAs(usuarioDeKardex())
            ->get(route('admin.alm.kardex.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('movimientos.data', 2)
                // El más reciente arriba: es lo que se viene a consultar.
                ->where('movimientos.data.0.cantidad', -30)
                ->where('movimientos.data.0.saldo_despues', 70)
                ->where('movimientos.data.1.cantidad', 100)
                ->where('movimientos.data.1.saldo_despues', 100));
    });

    it('enseña el saldo que dejó cada movimiento, no uno recalculado', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        $ledger = app(AlmacenLedger::class);

        foreach ([50, -20, 30, -10] as $cantidad) {
            $ledger->registrarPorProducto(
                $almacen->id,
                $producto->id,
                $cantidad > 0 ? MovimientoTipo::Entrada : MovimientoTipo::Salida,
                $cantidad,
                $cantidad > 0 ? 5 : null,
            );
        }

        $this->actingAs(usuarioDeKardex())
            ->get(route('admin.alm.kardex.index', [
                'almacen_id' => $almacen->id,
                'producto_id' => $producto->id,
            ]))
            ->assertInertia(fn ($page) => $page
                ->where('movimientos.data.0.saldo_despues', 50)
                ->where('movimientos.data.1.saldo_despues', 60)
                ->where('movimientos.data.2.saldo_despues', 30)
                ->where('movimientos.data.3.saldo_despues', 50));
    });

    it('los totales van sobre el filtro completo, no sobre la pagina', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        $ledger = app(AlmacenLedger::class);

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 10);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 40, 10);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Salida, -25);

        $this->actingAs(usuarioDeKardex())
            ->get(route('admin.alm.kardex.index'))
            ->assertInertia(fn ($page) => $page
                ->where('totales.movimientos', 3)
                ->where('totales.entradas', 140)
                ->where('totales.salidas', -25));
    });

    it('filtra por almacen, articulo y tipo', function () {
        $ag = Almacen::factory()->create();
        $fak = Almacen::factory()->create();
        $tornillo = Producto::factory()->create();
        $electrodo = Producto::factory()->create();
        $ledger = app(AlmacenLedger::class);

        $ledger->registrarPorProducto($ag->id, $tornillo->id, MovimientoTipo::Entrada, 100, 10);
        $ledger->registrarPorProducto($ag->id, $electrodo->id, MovimientoTipo::Entrada, 50, 60);
        $ledger->registrarPorProducto($fak->id, $tornillo->id, MovimientoTipo::Entrada, 20, 10);
        $ledger->registrarPorProducto($ag->id, $tornillo->id, MovimientoTipo::Salida, -10);

        $usuario = usuarioDeKardex();

        $this->actingAs($usuario)
            ->get(route('admin.alm.kardex.index', ['almacen_id' => $ag->id]))
            ->assertInertia(fn ($page) => $page->has('movimientos.data', 3));

        $this->actingAs($usuario)
            ->get(route('admin.alm.kardex.index', ['producto_id' => $tornillo->id]))
            ->assertInertia(fn ($page) => $page->has('movimientos.data', 3));

        $this->actingAs($usuario)
            ->get(route('admin.alm.kardex.index', ['tipo' => 'salida']))
            ->assertInertia(fn ($page) => $page->has('movimientos.data', 1));
    });

    it('solo muestra los almacenes que el usuario puede ver', function () {
        $suyo = Almacen::factory()->create();
        $ajeno = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        $ledger = app(AlmacenLedger::class);

        $ledger->registrarPorProducto($suyo->id, $producto->id, MovimientoTipo::Entrada, 10, 5);
        $ledger->registrarPorProducto($ajeno->id, $producto->id, MovimientoTipo::Entrada, 10, 5);

        $usuario = User::factory()->create();
        Permission::firstOrCreate(['name' => 'alm.kardex.ver', 'guard_name' => 'web']);
        $usuario->givePermissionTo('alm.kardex.ver');
        $suyo->usuarios()->attach($usuario->getKey());

        $this->actingAs($usuario)
            ->get(route('admin.alm.kardex.index'))
            ->assertInertia(fn ($page) => $page
                ->has('movimientos.data', 1)
                ->where('totales.movimientos', 1));
    });

    it('cierra la pantalla a quien no tiene permiso', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.alm.kardex.index'))
            ->assertForbidden();
    });
});

describe('existencias', function () {
    it('trae el saldo, el costo y el valor de cada renglon', function () {
        $almacen = Almacen::factory()->create(['clave' => 'AG']);
        $producto = Producto::factory()->create(['codigo' => 'TOR-0012']);

        app(AlmacenLedger::class)->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 4.5
        );

        $this->actingAs(usuarioDeKardex())
            ->get(route('admin.alm.existencias.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('existencias.data', 1)
                ->where('existencias.data.0.almacen', 'AG')
                ->where('existencias.data.0.codigo', 'TOR-0012')
                ->where('existencias.data.0.cantidad', 100)
                ->where('existencias.data.0.costo_promedio', 4.5)
                ->where('existencias.data.0.valor', 450));
    });

    it('el resumen suma el inventario completo y señala lo que hay que atender', function () {
        $almacen = Almacen::factory()->create();
        $ledger = app(AlmacenLedger::class);

        $conSaldo = Producto::factory()->create();
        $vacio = Producto::factory()->create();
        $negativo = Producto::factory()->create();

        $ledger->registrarPorProducto($almacen->id, $conSaldo->id, MovimientoTipo::Entrada, 100, 10);
        $ledger->registrarPorProducto($almacen->id, $vacio->id, MovimientoTipo::Entrada, 5, 2);
        $ledger->registrarPorProducto($almacen->id, $vacio->id, MovimientoTipo::Salida, -5);
        $ledger->registrarPorProducto(
            $almacen->id, $negativo->id, MovimientoTipo::Ajuste, -3, permitirNegativo: true
        );

        $this->actingAs(usuarioDeKardex())
            ->get(route('admin.alm.existencias.index'))
            ->assertInertia(fn ($page) => $page
                ->where('resumen.renglones', 3)
                ->where('resumen.con_saldo', 2)
                ->where('resumen.valor', 1000)
                // Nadie ha acomodado nada todavía: es la lista de trabajo.
                ->where('resumen.sin_acomodar', 2)
                ->where('resumen.en_negativo', 1));
    });

    it('filtra por ubicacion y por lo que esta sin acomodar', function () {
        $almacen = Almacen::factory()->create();
        $rack = Ubicacion::factory()->de($almacen)->create();
        $ledger = app(AlmacenLedger::class);

        $acomodado = Producto::factory()->create();
        $suelto = Producto::factory()->create();

        $ledger->registrarPorProducto($almacen->id, $acomodado->id, MovimientoTipo::Entrada, 10, 5);
        $ledger->registrarPorProducto($almacen->id, $suelto->id, MovimientoTipo::Entrada, 10, 5);
        $ledger->bloquear($almacen->id, $acomodado->id)->update(['ubicacion_id' => $rack->id]);

        $usuario = usuarioDeKardex();

        $this->actingAs($usuario)
            ->get(route('admin.alm.existencias.index', [
                'almacen_id' => $almacen->id,
                'ubicacion_id' => $rack->id,
            ]))
            ->assertInertia(fn ($page) => $page->has('existencias.data', 1)
                ->where('existencias.data.0.producto_id', $acomodado->id));

        $this->actingAs($usuario)
            ->get(route('admin.alm.existencias.index', ['sin_acomodar' => 1]))
            ->assertInertia(fn ($page) => $page->has('existencias.data', 1)
                ->where('existencias.data.0.producto_id', $suelto->id));
    });

    it('solo ofrece ubicaciones cuando hay un almacen elegido', function () {
        $almacen = Almacen::factory()->create();
        Ubicacion::factory()->de($almacen)->create();

        $usuario = usuarioDeKardex();

        // El «Rack A-1» de AG no es el de FAK: el filtro sin almacén no
        // significaría nada.
        $this->actingAs($usuario)
            ->get(route('admin.alm.existencias.index'))
            ->assertInertia(fn ($page) => $page->has('ubicaciones', 0));

        $this->actingAs($usuario)
            ->get(route('admin.alm.existencias.index', ['almacen_id' => $almacen->id]))
            ->assertInertia(fn ($page) => $page->has('ubicaciones', 1));
    });

    it('cierra la pantalla a quien no tiene permiso', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.alm.existencias.index'))
            ->assertForbidden();
    });
});

describe('el almacen deja de borrarse cuando tiene historia', function () {
    it('no borra un almacen con movimientos', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        app(AlmacenLedger::class)->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Entrada, 10, 5
        );

        $usuario = User::factory()->create();
        foreach (['alm.almacenes.eliminar', 'alm.almacenes.ver-todos'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }
        $usuario->givePermissionTo(['alm.almacenes.eliminar', 'alm.almacenes.ver-todos']);

        $this->actingAs($usuario)
            ->delete(route('admin.alm.almacenes.destroy', $almacen))
            ->assertSessionHasErrors('almacen');

        expect(Almacen::whereKey($almacen->id)->exists())->toBeTrue();
    });
});
