<?php

use App\Enums\Alm\MovimientoTipo;
use App\Exceptions\Alm\ExistenciaInsuficienteException;
use App\Models\Alm\Almacen;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Costos\Producto;
use App\Services\Alm\AlmacenLedger;

/**
 * @return array{0: AlmacenLedger, 1: Almacen, 2: Producto}
 */
function escenarioLedger(): array
{
    return [app(AlmacenLedger::class), Almacen::factory()->create(), Producto::factory()->create()];
}

describe('saldo', function () {
    it('crea la existencia en la primera carga', function () {
        [$ledger, $almacen, $producto] = escenarioLedger();

        expect(Existencia::count())->toBe(0);

        $mov = $ledger->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 4.35
        );

        expect($mov)->not->toBeNull()
            ->and((float) $mov->saldo_antes)->toBe(0.0)
            ->and((float) $mov->saldo_despues)->toBe(100.0);

        $existencia = Existencia::firstOrFail();

        expect((float) $existencia->cantidad)->toBe(100.0)
            ->and((float) $existencia->costo_promedio)->toBe(4.35)
            ->and((float) $existencia->valor)->toBe(435.0)
            ->and($existencia->ultimo_movimiento_at)->not->toBeNull();
    });

    it('encadena saldo_antes con el saldo_despues del movimiento anterior', function () {
        [$ledger, $almacen, $producto] = escenarioLedger();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 10);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Salida, -30);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 50, 12);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Salida, -20);

        $movimientos = Movimiento::query()->cronologico()->get();

        expect($movimientos)->toHaveCount(4);

        $anterior = null;

        foreach ($movimientos as $movimiento) {
            if ($anterior !== null) {
                expect((float) $movimiento->saldo_antes)->toBe((float) $anterior->saldo_despues);
            }

            expect((float) $movimiento->saldo_despues)
                ->toBe((float) $movimiento->saldo_antes + (float) $movimiento->cantidad);

            $anterior = $movimiento;
        }

        // La invariante que hace confiable al kardex: el último asiento dice el
        // saldo vigente sin tener que sumar la columna.
        expect((float) $anterior->saldo_despues)
            ->toBe((float) Existencia::firstOrFail()->cantidad)
            ->toBe(100.0);
    });

    it('rechaza sacar mas de lo que hay', function () {
        [$ledger, $almacen, $producto] = escenarioLedger();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 10, 5);

        expect(fn () => $ledger->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Salida, -11
        ))->toThrow(ExistenciaInsuficienteException::class);

        // Nada quedó a medias: el saldo sigue donde estaba y no hay asiento huérfano.
        expect((float) Existencia::firstOrFail()->cantidad)->toBe(10.0)
            ->and(Movimiento::count())->toBe(1);
    });

    it('deja el saldo en cero exacto cuando se lleva todo', function () {
        [$ledger, $almacen, $producto] = escenarioLedger();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 33.333, 7.77);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Salida, -33.333);

        $existencia = Existencia::firstOrFail();

        expect((float) $existencia->cantidad)->toBe(0.0)
            // Sin esto, los centavos de redondear cuatro decimales dejarían un
            // almacén vacío «valiendo» unos pesos.
            ->and((float) $existencia->valor)->toBe(0.0);
    });
});

describe('costeo', function () {
    it('promedia ponderado solo en las cargas con costo', function () {
        [$ledger, $almacen, $producto] = escenarioLedger();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 10);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 20);

        // (100*10 + 100*20) / 200
        expect((float) Existencia::firstOrFail()->costo_promedio)->toBe(15.0);
    });

    it('la salida no mueve el promedio y sale al costo vigente', function () {
        [$ledger, $almacen, $producto] = escenarioLedger();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 15);
        $salida = $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Salida, -40);

        expect((float) $salida->costo_unitario)->toBe(15.0)
            ->and((float) Existencia::firstOrFail()->costo_promedio)->toBe(15.0)
            ->and((float) Existencia::firstOrFail()->valor)->toBe(900.0);
    });

    it('la pieza con serie sale a su propio costo, no al promedio', function () {
        [$ledger, $almacen] = escenarioLedger();
        $producto = Producto::factory()->porPieza()->create();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 1, 2180);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 1, 2340);

        // Se va la cara: quedan 2180 de valor, no el promedio de 2260.
        $baja = $ledger->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Salida, -1, 2340
        );

        expect((float) $baja->costo_unitario)->toBe(2340.0)
            ->and((float) Existencia::firstOrFail()->valor)->toBe(2180.0);
    });

    it('una carga sin costo no inventa valor ni mueve el promedio', function () {
        [$ledger, $almacen, $producto] = escenarioLedger();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 10);
        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Ajuste, 20);

        $existencia = Existencia::firstOrFail();

        expect((float) $existencia->cantidad)->toBe(120.0)
            ->and((float) $existencia->costo_promedio)->toBe(10.0)
            ->and((float) $existencia->valor)->toBe(1200.0);
    });
});

describe('reglas del ledger', function () {
    it('no toca el kardex de lo que no controla inventario', function () {
        [$ledger, $almacen] = escenarioLedger();
        $flete = Producto::factory()->sinInventario()->create();

        $movimiento = $ledger->registrarPorProducto(
            $almacen->id, $flete->id, MovimientoTipo::Entrada, 1, 8500
        );

        // Devuelve null en vez de reventar: quien recibe una orden completa no
        // tiene por qué filtrar los servicios renglón por renglón.
        expect($movimiento)->toBeNull()
            ->and(Existencia::count())->toBe(0)
            ->and(Movimiento::count())->toBe(0);
    });

    it('tampoco toca el de lo que Compras tecleo sin codigo', function () {
        [$ledger, $almacen] = escenarioLedger();
        $sinClasificar = Producto::factory()->sinClasificar()->create();

        expect($ledger->registrarPorProducto(
            $almacen->id, $sinClasificar->id, MovimientoTipo::Entrada, 5, 100
        ))->toBeNull();
    });

    it('rechaza una salida capturada en positivo', function () {
        [$ledger, $almacen, $producto] = escenarioLedger();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 10);

        expect(fn () => $ledger->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Salida, 30
        ))->toThrow(InvalidArgumentException::class);
    });

    it('rechaza un movimiento de cantidad cero', function () {
        [$ledger, $almacen, $producto] = escenarioLedger();

        expect(fn () => $ledger->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Entrada, 0
        ))->toThrow(InvalidArgumentException::class);
    });

    it('el ajuste es el unico que puede dejar el saldo en negativo', function () {
        [$ledger, $almacen, $producto] = escenarioLedger();

        $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 10, 5);

        $ledger->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Ajuste, -25, permitirNegativo: true
        );

        expect((float) Existencia::firstOrFail()->cantidad)->toBe(-15.0)
            ->and(Existencia::enNegativo()->count())->toBe(1);
    });

    it('sella el folio del documento en el movimiento', function () {
        [$ledger, $almacen, $producto] = escenarioLedger();

        $movimiento = $ledger->registrarPorProducto(
            $almacen->id,
            $producto->id,
            MovimientoTipo::Entrada,
            100,
            10,
            referencia: 'REC-260801',
            observaciones: 'Primer viaje',
        );

        expect($movimiento->referencia)->toBe('REC-260801')
            ->and($movimiento->observaciones)->toBe('Primer viaje');
    });

    it('separa el saldo por almacen', function () {
        [$ledger, $ag, $producto] = escenarioLedger();
        $fak = Almacen::factory()->create();

        $ledger->registrarPorProducto($ag->id, $producto->id, MovimientoTipo::Entrada, 100, 10);
        $ledger->registrarPorProducto($fak->id, $producto->id, MovimientoTipo::Entrada, 40, 12);

        expect($ledger->disponible($ag->id, $producto->id))->toBe(100.0)
            ->and($ledger->disponible($fak->id, $producto->id))->toBe(40.0)
            ->and(Existencia::count())->toBe(2);
    });
});
