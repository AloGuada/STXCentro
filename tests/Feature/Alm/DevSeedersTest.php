<?php

use App\Enums\Alm\MovimientoTipo;
use App\Enums\Alm\PedidoEstatus;
use App\Enums\Alm\TransferenciaEstatus;
use App\Models\Alm\Activo;
use App\Models\Alm\Ajuste;
use App\Models\Alm\Asignacion;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Alm\Pedido;
use App\Models\Alm\Salida;
use App\Models\Alm\Transferencia;
use App\Models\Costos\OrdenCompra;
use App\Models\User;
use Database\Seeders\AlmDevSeeder;
use Database\Seeders\AlmEntradaDevSeeder;
use Database\Seeders\AlmMovimientosDevSeeder;
use Database\Seeders\AlmPedidosHerramientaDevSeeder;

/**
 * Los seeders de ejemplo no son código de producción, pero se apoyan en los
 * servicios del módulo y se rompen en silencio cuando una firma cambia: el
 * seeder sigue corriendo y lo que queda es una base con el saldo mal. Estas
 * pruebas son la alarma.
 */
beforeEach(function () {
    User::factory()->create();
});

function sembrarAlmacenDemo(): void
{
    test()->seed(AlmDevSeeder::class);
    test()->seed(AlmEntradaDevSeeder::class);
    test()->seed(AlmMovimientosDevSeeder::class);
}

test('las entradas dejan el material asignado a la obra que lo compró', function () {
    sembrarAlmacenDemo();

    // Tres obras distintas comprando, que es lo que hace que la pantalla de
    // existencias tenga un desglose que enseñar.
    expect(Asignacion::vivas()->distinct()->count('obra_id'))->toBeGreaterThanOrEqual(3);

    // Ninguna entrada inventa dueño: el que tiene obra la sacó de su orden.
    $conObra = Movimiento::query()
        ->where('tipo', MovimientoTipo::Entrada)
        ->whereNotNull('obra_id')
        ->count();

    expect($conObra)->toBeGreaterThan(0);
});

test('deja varios renglones repartidos entre obras y con tramo libre', function () {
    sembrarAlmacenDemo();

    $conDuenio = Existencia::query()
        ->whereHas('asignaciones', fn ($query) => $query->vivas())
        ->with('asignaciones')
        ->get();

    // Lo que se revisa en la pantalla de existencias es el desglose por obra, y
    // con un solo renglón repartido no se ve si agrupa bien. Si el bloque de
    // relleno deja de sembrar, esto avisa.
    expect($conDuenio->count())->toBeGreaterThanOrEqual(6);

    // Y en alguno sobra material sin comprometer: un renglón repartido al cien
    // esconde justo la pregunta con la que se abre la pantalla, que es cuánto
    // queda para repartir.
    $conLibre = $conDuenio->filter(
        fn (Existencia $existencia): bool => (float) $existencia->cantidad
            - (float) $existencia->asignaciones->sum('cantidad') > 0
    );

    expect($conLibre)->not->toBeEmpty();
});

test('lo asignado nunca rebasa la existencia ni se va a negativo', function () {
    sembrarAlmacenDemo();

    expect(Existencia::where('cantidad', '<', 0)->count())->toBe(0)
        ->and(Asignacion::where('cantidad', '<', 0)->count())->toBe(0);

    // La invariante que sostiene todo el reparto: lo repartido cabe en lo que
    // hay. Si un servicio deja de descontar la partición, revienta aquí.
    foreach (Existencia::with('asignaciones')->get() as $existencia) {
        expect((float) $existencia->asignaciones->sum('cantidad'))
            ->toBeLessThanOrEqual((float) $existencia->cantidad + 0.001);
    }
});

test('siembra los documentos de cada escenario', function () {
    sembrarAlmacenDemo();

    expect(Salida::whereNotNull('cancelada_at')->count())->toBe(1)
        ->and(Transferencia::where('estatus', TransferenciaEstatus::EnTransito)->count())->toBe(1)
        ->and(Transferencia::where('estatus', TransferenciaEstatus::Recibida)->count())->toBe(1)
        ->and(Ajuste::count())->toBe(1)
        ->and(Activo::count())->toBe(3);

    // Los cuatro estatus de pedido que se ven en el listado.
    expect(Pedido::pluck('estatus')->all())->toEqualCanonicalizing([
        PedidoEstatus::Borrador,
        PedidoEstatus::Pendiente,
        PedidoEstatus::Aprobado,
        PedidoEstatus::Rechazado,
    ]);
});

test('la transferencia con faltante carga en el destino sólo lo confirmado', function () {
    sembrarAlmacenDemo();

    $recibida = Transferencia::where('estatus', TransferenciaEstatus::Recibida)->sole();
    $detalle = $recibida->detalles()->sole();

    // Las cantidades las dimensiona el seeder según lo que haya libre, así que
    // lo que se afirma es la relación entre ellas, no el número.
    expect((float) $detalle->cantidad_recibida)->toBeLessThan((float) $detalle->cantidad_enviada)
        // El faltante no genera un tercer movimiento; sólo gana responsable.
        ->and($recibida->faltante_responsable_id)->not->toBeNull();

    $cargado = Movimiento::query()
        ->where('documento_type', $recibida->getMorphClass())
        ->where('documento_id', $recibida->getKey())
        ->where('tipo', MovimientoTipo::TransferenciaEntrada)
        ->sum('cantidad');

    // El destino carga lo confirmado, no lo enviado: la merma se quedó en el
    // asiento de salida del origen.
    expect((float) $cargado)->toBe((float) $detalle->cantidad_recibida);
});

test('el conteo graba el renglón exacto pero no lo manda al kardex', function () {
    sembrarAlmacenDemo();

    $ajuste = Ajuste::with('detalles')->sole();
    $exactos = $ajuste->detalles->where('diferencia', 0.0);

    expect($exactos)->toHaveCount(1);

    $movimientos = Movimiento::query()
        ->where('documento_type', $ajuste->getMorphClass())
        ->where('documento_id', $ajuste->getKey())
        ->pluck('producto_id');

    expect($movimientos)->not->toContain($exactos->first()->producto_id)
        ->and($movimientos)->toHaveCount(2);
});

test('correrlos dos veces no duplica nada', function () {
    sembrarAlmacenDemo();

    $antes = [
        'ordenes' => OrdenCompra::count(),
        'movimientos' => Movimiento::count(),
        'salidas' => Salida::count(),
        'transferencias' => Transferencia::count(),
        'ajustes' => Ajuste::count(),
        'pedidos' => Pedido::count(),
        'activos' => Activo::count(),
        'asignado' => (float) Asignacion::sum('cantidad'),
    ];

    sembrarAlmacenDemo();

    expect(OrdenCompra::count())->toBe($antes['ordenes'])
        ->and(Movimiento::count())->toBe($antes['movimientos'])
        ->and(Salida::count())->toBe($antes['salidas'])
        ->and(Transferencia::count())->toBe($antes['transferencias'])
        ->and(Ajuste::count())->toBe($antes['ajustes'])
        ->and(Pedido::count())->toBe($antes['pedidos'])
        ->and(Activo::count())->toBe($antes['activos'])
        ->and((float) Asignacion::sum('cantidad'))->toBe($antes['asignado']);
});

test('los pedidos de herramienta de ejemplo cubren los tres casos y son idempotentes', function () {
    sembrarAlmacenDemo();
    test()->seed(AlmPedidosHerramientaDevSeeder::class);
    test()->seed(AlmPedidosHerramientaDevSeeder::class);

    $pedidos = \App\Models\Alm\Pedido::query()
        ->where('observaciones', 'like', '%[demo-alm-herramienta]%')
        ->with('detalles.articulo')
        ->get();

    // Uno solo de insumos, uno solo de herramienta y uno mixto; correrlo dos
    // veces no los duplica.
    expect($pedidos)->toHaveCount(3)
        ->and($pedidos->filter(fn ($p) => $p->pideHerramienta())->count())->toBe(2)
        ->and($pedidos->filter(fn ($p) => ! $p->pideHerramienta())->count())->toBe(1)
        ->and(\App\Models\Alm\Pedido::query()->surtiblesConPrestamo()->count())->toBe(2);

    // La extensión sin serie quedó con existencia real, por el registrador.
    $extension = \App\Models\Alm\Articulo::query()->activosPorCantidad()->firstOrFail();

    expect((float) $extension->existencias()->sum('cantidad'))->toBe(12.0);
});
