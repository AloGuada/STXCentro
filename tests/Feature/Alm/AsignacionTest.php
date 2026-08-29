<?php

use App\Enums\Alm\MovimientoTipo;
use App\Exceptions\Alm\AsignacionAjenaException;
use App\Models\Alm\Almacen;
use App\Models\Alm\Asignacion;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Producto;
use App\Models\Obra;
use App\Services\Alm\AlmacenLedger;
use App\Services\Alm\RegistradorEntradaAlmacen;

/**
 * La partición del saldo por obra.
 *
 * Lo comprometido no es un inventario aparte: es el reparto del mismo renglón de
 * `alm_existencias`, y lo libre es lo que sobra de repartir. Estas pruebas
 * cuidan sobre todo el invariante —nadie puede comprometer más de lo que hay— y
 * el orden en que se consume, que es lo que hace que la asignación sirva de algo.
 */

/**
 * @return array{0: AlmacenLedger, 1: Almacen, 2: Producto}
 */
function escenarioAsignacion(): array
{
    return [
        app(AlmacenLedger::class),
        Almacen::factory()->create(),
        Producto::factory()->create(['controla_inventario' => true]),
    ];
}

function asignadoA(Obra $obra): float
{
    return (float) Asignacion::where('obra_id', $obra->id)->value('cantidad');
}

describe('la recepción reparte el material', function () {
    it('asigna lo recibido a la obra del centro de costos de la orden', function () {
        $obra = Obra::factory()->create();
        $rubro = ObraRubro::factory()->create(['obra_id' => $obra->id]);
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['controla_inventario' => true]);

        $orden = OrdenCompra::factory()->create();
        $partida = OrdenCompraDetalle::factory()->create([
            'orden_compra_id' => $orden->id,
            'producto_id' => $producto->id,
            'obra_rubro_id' => $rubro->id,
        ]);

        $entrega = Entrega::factory()->create([
            'orden_compra_id' => $orden->id,
            'almacen_id' => $almacen->id,
        ]);
        EntregaDetalle::factory()->create([
            'entrega_id' => $entrega->id,
            'orden_compra_detalle_id' => $partida->id,
            'producto_id' => $producto->id,
            'cantidad_recibida' => 100,
        ]);

        app(RegistradorEntradaAlmacen::class)->aplicar($entrega);

        $existencia = Existencia::firstOrFail();

        // Nadie tecleó la obra: salió de la partida → centro de costos → obra.
        expect((float) $existencia->cantidad)->toBe(100.0)
            ->and(asignadoA($obra))->toBe(100.0)
            ->and($existencia->libre())->toBe(0.0)
            ->and(Movimiento::firstOrFail()->obra_id)->toBe($obra->id);
    });

    it('deja libre lo que entra sin orden', function () {
        [$ledger, $almacen, $producto] = escenarioAsignacion();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 10);

        $existencia = Existencia::firstOrFail();

        // Sin compra de por medio no hay presupuesto de dónde deducir el dueño.
        expect(Asignacion::count())->toBe(0)
            ->and($existencia->libre())->toBe(100.0)
            ->and(Movimiento::firstOrFail()->obra_id)->toBeNull();
    });

    it('devuelve a su obra lo que revierte una recepción cancelada', function () {
        $obra = Obra::factory()->create();
        $rubro = ObraRubro::factory()->create(['obra_id' => $obra->id]);
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['controla_inventario' => true]);

        $orden = OrdenCompra::factory()->create();
        $partida = OrdenCompraDetalle::factory()->create([
            'orden_compra_id' => $orden->id,
            'producto_id' => $producto->id,
            'obra_rubro_id' => $rubro->id,
        ]);

        $entrega = Entrega::factory()->create([
            'orden_compra_id' => $orden->id,
            'almacen_id' => $almacen->id,
        ]);
        EntregaDetalle::factory()->create([
            'entrega_id' => $entrega->id,
            'orden_compra_detalle_id' => $partida->id,
            'producto_id' => $producto->id,
            'cantidad_recibida' => 40,
        ]);

        $registrador = app(RegistradorEntradaAlmacen::class);
        $registrador->aplicar($entrega);

        expect(asignadoA($obra))->toBe(40.0);

        $registrador->revertir($entrega, 'Se capturó de más');

        // El saldo y la partición vuelven juntos: si sólo bajara el saldo, esa
        // obra quedaría con 40 comprometidos sobre una existencia en cero.
        expect((float) Existencia::firstOrFail()->cantidad)->toBe(0.0)
            ->and(asignadoA($obra))->toBe(0.0);
    });
});

describe('la salida consume en orden', function () {
    it('gasta primero lo de su obra', function () {
        [$ledger, $almacen, $producto] = escenarioAsignacion();
        $obra = Obra::factory()->create();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 10, obraId: $obra->id);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 50, 10);

        $ledger->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Salida, -30, obraId: $obra->id
        );

        $existencia = Existencia::firstOrFail();

        // Lo libre sigue intacto: gastar primero lo propio es lo que hace que
        // apartar material signifique algo.
        expect(asignadoA($obra))->toBe(70.0)
            ->and($existencia->libre())->toBe(50.0)
            ->and((float) $existencia->cantidad)->toBe(120.0);
    });

    it('pasa a lo libre cuando lo suyo no alcanza, y deja un asiento por origen', function () {
        [$ledger, $almacen, $producto] = escenarioAsignacion();
        $obra = Obra::factory()->create();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 20, 10, obraId: $obra->id);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 80, 10);

        $ledger->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Salida, -30, obraId: $obra->id
        );

        $salidas = Movimiento::where('tipo', MovimientoTipo::Salida->value)->cronologico()->get();

        // Dos asientos, no uno con la suma: es lo que permite que la
        // cancelación devuelva cada parte a donde estaba.
        expect($salidas)->toHaveCount(2)
            ->and($salidas[0]->obra_id)->toBe($obra->id)
            ->and((float) $salidas[0]->cantidad)->toBe(-20.0)
            ->and($salidas[1]->obra_id)->toBeNull()
            ->and((float) $salidas[1]->cantidad)->toBe(-10.0);

        expect(asignadoA($obra))->toBe(0.0)
            ->and(Existencia::firstOrFail()->libre())->toBe(70.0);
    });

    it('se frena ante lo comprometido con otra obra', function () {
        [$ledger, $almacen, $producto] = escenarioAsignacion();
        $ajena = Obra::factory()->create();
        $propia = Obra::factory()->create();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 10, obraId: $ajena->id);

        // Hay 100 en el anaquel y el sistema no lo deja sacar: por eso el
        // mensaje tiene que distinguirse del de «no hay existencia».
        expect(fn () => $ledger->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Salida, -30, obraId: $propia->id
        ))->toThrow(AsignacionAjenaException::class);

        expect(asignadoA($ajena))->toBe(100.0)
            ->and((float) Existencia::firstOrFail()->cantidad)->toBe(100.0);
    });

    it('con el permiso especial se lleva lo ajeno y se lo descuenta a esa obra', function () {
        [$ledger, $almacen, $producto] = escenarioAsignacion();
        $ajena = Obra::factory()->create();
        $propia = Obra::factory()->create();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 10, obraId: $ajena->id);

        $ledger->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Salida, -30,
            obraId: $propia->id, permitirAjena: true
        );

        expect(asignadoA($ajena))->toBe(70.0)
            ->and(Movimiento::where('tipo', MovimientoTipo::Salida->value)->firstOrFail()->obra_id)
            ->toBe($ajena->id);
    });

    it('no pide permiso para lo libre aunque haya material de otra obra', function () {
        [$ledger, $almacen, $producto] = escenarioAsignacion();
        $ajena = Obra::factory()->create();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 40, 10, obraId: $ajena->id);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 60, 10);

        // Sin obra —consumo interno de planta— y hay 60 sin dueño: alcanza.
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Salida, -60);

        expect(asignadoA($ajena))->toBe(40.0)
            ->and(Existencia::firstOrFail()->libre())->toBe(0.0);
    });
});

describe('el invariante', function () {
    it('nunca deja comprometido más de lo que hay', function () {
        [$ledger, $almacen, $producto] = escenarioAsignacion();
        $unaObra = Obra::factory()->create();
        $otraObra = Obra::factory()->create();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 10, obraId: $unaObra->id);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 50, 10, obraId: $otraObra->id);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 30, 10);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Salida, -120, obraId: $unaObra->id, permitirAjena: true);

        $existencia = Existencia::firstOrFail();

        expect((float) Asignacion::sum('cantidad'))->toBeLessThanOrEqual((float) $existencia->cantidad)
            ->and($existencia->libre())->toBeGreaterThanOrEqual(0.0);
    });

    it('la partición se puede reconstruir desde el kardex', function () {
        [$ledger, $almacen, $producto] = escenarioAsignacion();
        $obra = Obra::factory()->create();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 10, obraId: $obra->id);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 40, 10);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Salida, -25, obraId: $obra->id);

        // El mismo papel que juega `saldo_despues` con la existencia: la columna
        // cacheada no es una segunda verdad, es el kardex sumado.
        $delKardex = (float) Movimiento::where('obra_id', $obra->id)->sum('cantidad');

        expect($delKardex)->toBe(asignadoA($obra))->toBe(75.0);
    });

    it('el saldo en negativo se lo come lo libre, no la asignación de una obra', function () {
        [$ledger, $almacen, $producto] = escenarioAsignacion();
        $obra = Obra::factory()->create();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 50, 10, obraId: $obra->id);

        // Un ajuste puede dejar la existencia bajo cero. Eso no debe convertirse
        // en quitarle material a una obra por la puerta de atrás.
        $ledger->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Ajuste, -80, permitirNegativo: true
        );

        expect((float) Existencia::firstOrFail()->cantidad)->toBe(-30.0)
            ->and(asignadoA($obra))->toBe(50.0)
            ->and(Movimiento::where('tipo', MovimientoTipo::Ajuste->value)->count())->toBe(1);
    });
});
