<?php

use App\Enums\Alm\MovimientoTipo;
use App\Enums\Alm\PedidoEstatus;
use App\Models\Alm\Almacen;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Alm\Pedido;
use App\Models\Alm\Salida;
use App\Models\Costos\Producto;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Prod\GrupoTrabajo;
use App\Models\User;
use App\Services\Alm\AlmacenLedger;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>  $permisos
 */
function usuarioDeSalidas(array $permisos = ['alm.salidas.ver', 'alm.salidas.crear', 'alm.pedidos.ver', 'alm.pedidos.crear', 'alm.pedidos.cancelar']): User
{
    $user = User::factory()->create();

    $nombres = [...$permisos, 'alm.almacenes.ver-todos'];

    foreach ($nombres as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($nombres);

    return $user;
}

/** Siembra saldo sin pasar por un documento. */
function sembrar(Almacen $almacen, Producto $producto, float $cantidad, float $costo = 10): void
{
    app(AlmacenLedger::class)->registrarPorProducto(
        $almacen->id, $producto->id, MovimientoTipo::Entrada, $cantidad, $costo
    );
}

/**
 * @param  list<array<string, mixed>>  $detalles
 * @return array<string, mixed>
 */
function salidaValida(Almacen $almacen, array $detalles, array $extra = []): array
{
    return [
        'almacen_id' => $almacen->id,
        // El almacen de la factoria es central: una salida directa de planta
        // tiene que decir a que departamento se le carga.
        'departamento_id' => Departamento::factory()->create()->id,
        'recibe_nombre' => 'Cuadrilla 3',
        'fecha' => now()->toDateString(),
        'detalles' => $detalles,
        ...$extra,
    ];
}

describe('la salida descuenta', function () {
    it('resta del kardex y sella el costo con el que salio', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 100, 4.5);

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad' => 30],
            ]))
            ->assertRedirect();

        $salida = Salida::firstOrFail();

        expect((float) Existencia::firstOrFail()->cantidad)->toBe(70.0)
            ->and((float) $salida->detalles()->firstOrFail()->costo_unitario)->toBe(4.5)
            ->and($salida->folio)->toStartWith('SAL-');
    });

    it('no deja sacar mas de lo que hay', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 10);

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad' => 11],
            ]))
            ->assertSessionHasErrors('detalles.0.cantidad');

        expect(Salida::count())->toBe(0)
            ->and((float) Existencia::firstOrFail()->cantidad)->toBe(10.0);
    });

    it('suma los renglones del mismo articulo antes de comparar contra el saldo', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 10);

        // Cada renglón cabe por separado, pero juntos se llevan el doble.
        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad' => 6],
                ['articulo_id' => articuloDe($producto), 'cantidad' => 6],
            ]))
            ->assertSessionHasErrors('detalles.0.cantidad');

        expect(Salida::count())->toBe(0);
    });

    /** El gasto se reconoció en la compra: Almacén sólo controla artículos. */
    it('no toca el presupuesto', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 100);

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad' => 30],
            ]));

        expect(DB::table('costos_rubro_movimientos')->count())->toBe(0);
    });
});

describe('la cancelacion devuelve', function () {
    it('deja el reverso en el kardex sin borrar el documento', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 100, 5);

        $usuario = usuarioDeSalidas();

        $this->actingAs($usuario)->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
            ['articulo_id' => articuloDe($producto), 'cantidad' => 40],
        ]));

        $salida = Salida::firstOrFail();

        $this->actingAs($usuario)
            ->patch(route('admin.alm.salidas.cancelar', $salida), ['motivo' => 'Se capturó el almacén equivocado'])
            ->assertRedirect();

        expect((float) Existencia::firstOrFail()->cantidad)->toBe(100.0)
            ->and($salida->refresh()->estaCancelada())->toBeTrue()
            // El folio y los dos asientos quedan: es lo que explica después por
            // qué el saldo bajó y subió el mismo día.
            ->and(Movimiento::where('es_reverso', true)->count())->toBe(1)
            ->and(Salida::count())->toBe(1);
    });

    it('cancelar dos veces no devuelve dos veces', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 100, 5);

        $usuario = usuarioDeSalidas();
        $this->actingAs($usuario)->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
            ['articulo_id' => articuloDe($producto), 'cantidad' => 40],
        ]));

        $salida = Salida::firstOrFail();

        $this->actingAs($usuario)->patch(route('admin.alm.salidas.cancelar', $salida), ['motivo' => 'x']);
        $this->actingAs($usuario)
            ->patch(route('admin.alm.salidas.cancelar', $salida->refresh()), ['motivo' => 'otra vez'])
            ->assertSessionHasErrors('salida');

        expect((float) Existencia::firstOrFail()->cantidad)->toBe(100.0);
    });
});

describe('surtir un pedido', function () {
    it('el surtido parcial deja el pedido abierto y el completo lo cierra', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 5000);

        $pedido = Pedido::factory()->de($almacen)->create();
        $renglon = $pedido->detalles()->create([
            'producto_id' => $producto->id,
            'cantidad_solicitada' => 2000,
        ]);

        $usuario = usuarioDeSalidas();

        $this->actingAs($usuario)->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
            ['articulo_id' => articuloDe($producto), 'pedido_detalle_id' => $renglon->id, 'cantidad' => 800],
        ], ['pedido_id' => $pedido->id]));

        expect((float) $renglon->refresh()->cantidad_surtida)->toBe(800.0)
            ->and($pedido->refresh()->estatus)->toBe(PedidoEstatus::Aprobado)
            // Sigue debiendo, así que sigue apareciendo entre lo surtible.
            ->and(Pedido::surtibles($almacen->id)->count())->toBe(1);

        $this->actingAs($usuario)->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
            ['articulo_id' => articuloDe($producto), 'pedido_detalle_id' => $renglon->id, 'cantidad' => 1200],
        ], ['pedido_id' => $pedido->id]));

        expect((float) $renglon->refresh()->cantidad_surtida)->toBe(2000.0)
            ->and($pedido->refresh()->estatus)->toBe(PedidoEstatus::Surtido)
            ->and(Pedido::surtibles($almacen->id)->count())->toBe(0);
    });

    it('cancelar la salida regresa el pedido a lo que debia', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 5000);

        $pedido = Pedido::factory()->de($almacen)->create();
        $renglon = $pedido->detalles()->create([
            'producto_id' => $producto->id,
            'cantidad_solicitada' => 100,
        ]);

        $usuario = usuarioDeSalidas();

        $this->actingAs($usuario)->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
            ['articulo_id' => articuloDe($producto), 'pedido_detalle_id' => $renglon->id, 'cantidad' => 100],
        ], ['pedido_id' => $pedido->id]));

        expect($pedido->refresh()->estatus)->toBe(PedidoEstatus::Surtido);

        $this->actingAs($usuario)->patch(
            route('admin.alm.salidas.cancelar', Salida::firstOrFail()),
            ['motivo' => 'No se entregó'],
        );

        // La columna se recalcula, no se le resta: por eso la cancelación la
        // deja correcta sin lógica de reverso.
        expect((float) $renglon->refresh()->cantidad_surtida)->toBe(0.0)
            ->and($pedido->refresh()->estatus)->toBe(PedidoEstatus::Aprobado);
    });

    it('no deja surtir mas de lo que le falta al renglon', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 5000);

        $pedido = Pedido::factory()->de($almacen)->create();
        $renglon = $pedido->detalles()->create([
            'producto_id' => $producto->id,
            'cantidad_solicitada' => 100,
            'cantidad_surtida' => 80,
        ]);

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'pedido_detalle_id' => $renglon->id, 'cantidad' => 30],
            ], ['pedido_id' => $pedido->id]))
            ->assertSessionHasErrors('detalles.0.cantidad');
    });

    it('un pedido de obra no se surte con salida', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 5000);

        // Va a otro domicilio: lo surte una transferencia, y la obra confirma.
        $pedido = Pedido::factory()->de($almacen)->paraObra()->create();
        $renglon = $pedido->detalles()->create([
            'producto_id' => $producto->id,
            'cantidad_solicitada' => 100,
        ]);

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'pedido_detalle_id' => $renglon->id, 'cantidad' => 10],
            ], ['pedido_id' => $pedido->id]))
            ->assertSessionHasErrors('pedido_id');
    });

    it('no deja surtir un pedido de otro almacen', function () {
        $almacen = Almacen::factory()->create();
        $otro = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 500);

        $pedido = Pedido::factory()->de($otro)->create();

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad' => 10],
            ], ['pedido_id' => $pedido->id]))
            ->assertSessionHasErrors('pedido_id');
    });

    it('la salida directa no necesita pedido', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 100);

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad' => 10],
            ]))
            ->assertRedirect();

        expect(Salida::firstOrFail()->pedido_id)->toBeNull();
    });
});

describe('el pedido', function () {
    it('nace aprobado mientras no exista la matriz de aprobadores', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.pedidos.store'), [
                'almacen_id' => $almacen->id,
                'departamento_id' => Departamento::factory()->create()->id,
                'fecha' => now()->toDateString(),
                'fecha_requerida' => now()->addDay()->toDateString(),
                'detalles' => [['articulo_id' => articuloDe($producto), 'cantidad_solicitada' => 50]],
            ])
            ->assertRedirect();

        expect(Pedido::firstOrFail()->estatus)->toBe(PedidoEstatus::Aprobado)
            ->and(Pedido::firstOrFail()->folio)->toStartWith('PED-');
    });

    it('acepta consumo interno de planta, sin obra', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        // Fabricación, pintura y mantenimiento también piden material y no
        // cuelgan de ninguna obra.
        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.pedidos.store'), [
                'almacen_id' => $almacen->id,
                'departamento_id' => Departamento::factory()->create()->id,
                'recibe_nombre' => 'A. Pérez',
                'fecha' => now()->toDateString(),
                'fecha_requerida' => now()->addDay()->toDateString(),
                'detalles' => [['articulo_id' => articuloDe($producto), 'cantidad_solicitada' => 120]],
            ])
            ->assertSessionHasNoErrors();

        expect(Pedido::firstOrFail()->obra_id)->toBeNull()
            ->and(Pedido::firstOrFail()->seSurteConTransferencia())->toBeFalse();
    });

    it('en un pedido de obra no se anota quien recibe', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        // Ahí recibe el almacén destino, no una persona.
        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.pedidos.store'), [
                'almacen_id' => $almacen->id,
                'departamento_id' => Departamento::factory()->create()->id,
                'almacen_destino_id' => Almacen::factory()->deObra()->create()->id,
                'recibe_nombre' => 'A. Pérez',
                'fecha' => now()->toDateString(),
                'fecha_requerida' => now()->addDay()->toDateString(),
                'detalles' => [['articulo_id' => articuloDe($producto), 'cantidad_solicitada' => 10]],
            ])
            ->assertSessionHasErrors('recibe_nombre');
    });

    it('el destino es un almacen de obra y de ahi sale la obra', function () {
        $almacen = Almacen::factory()->create();
        $destino = Almacen::factory()->deObra()->create();
        $producto = Producto::factory()->create();

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.pedidos.store'), [
                'almacen_id' => $almacen->id,
                'departamento_id' => Departamento::factory()->create()->id,
                'almacen_destino_id' => $destino->id,
                'fecha' => now()->toDateString(),
                'fecha_requerida' => now()->addDay()->toDateString(),
                'detalles' => [['articulo_id' => articuloDe($producto), 'cantidad_solicitada' => 10]],
            ])
            ->assertSessionHasNoErrors();

        $pedido = Pedido::firstOrFail();

        expect($pedido->almacen_destino_id)->toBe($destino->id)
            ->and($pedido->obra_id)->toBe($destino->obra_id)
            ->and($pedido->seSurteConTransferencia())->toBeTrue();
    });

    it('no acepta de destino un almacen que no es de obra', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        // Sin obra es consumo interno: se deja el destino vacío, no se elige
        // otro almacén de planta.
        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.pedidos.store'), [
                'almacen_id' => $almacen->id,
                'departamento_id' => Departamento::factory()->create()->id,
                'almacen_destino_id' => Almacen::factory()->create()->id,
                'fecha' => now()->toDateString(),
                'fecha_requerida' => now()->addDay()->toDateString(),
                'detalles' => [['articulo_id' => articuloDe($producto), 'cantidad_solicitada' => 10]],
            ])
            ->assertSessionHasErrors('almacen_destino_id');

        expect(Pedido::count())->toBe(0);
    });

    /** El almacén decide si surte parcial o si hay que comprar. */
    it('si puede pedir mas de lo que hay', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 5);

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.pedidos.store'), [
                'almacen_id' => $almacen->id,
                'departamento_id' => Departamento::factory()->create()->id,
                'fecha' => now()->toDateString(),
                'fecha_requerida' => now()->addDay()->toDateString(),
                'detalles' => [['articulo_id' => articuloDe($producto), 'cantidad_solicitada' => 500]],
            ])
            ->assertSessionHasNoErrors();
    });

    it('no se puede necesitar el material antes de pedirlo', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.pedidos.store'), [
                'almacen_id' => $almacen->id,
                'departamento_id' => Departamento::factory()->create()->id,
                'fecha' => now()->toDateString(),
                'fecha_requerida' => now()->subDay()->toDateString(),
                'detalles' => [['articulo_id' => articuloDe($producto), 'cantidad_solicitada' => 10]],
            ])
            ->assertSessionHasErrors('fecha_requerida');
    });

    it('cancelar lo saca de lo que el almacen debe', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        $pedido = Pedido::factory()->de($almacen)->create();
        $pedido->detalles()->create(['articulo_id' => articuloDe($producto), 'cantidad_solicitada' => 10]);

        $this->actingAs(usuarioDeSalidas())
            ->patch(route('admin.alm.pedidos.cancelar', $pedido), ['motivo' => 'Ya no se necesita'])
            ->assertRedirect();

        expect($pedido->refresh()->estatus)->toBe(PedidoEstatus::Cancelado)
            ->and(Pedido::surtibles($almacen->id)->count())->toBe(0);
    });
});

describe('permisos', function () {
    it('ver salidas no alcanza para capturarlas', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 100);

        $usuario = usuarioDeSalidas(['alm.salidas.ver']);

        $this->actingAs($usuario)->get(route('admin.alm.salidas.index'))->assertOk();
        $this->actingAs($usuario)->get(route('admin.alm.salidas.create'))->assertForbidden();
        $this->actingAs($usuario)
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad' => 1],
            ]))
            ->assertForbidden();
    });

    it('no expone rutas para editar ni borrar una salida', function () {
        expect(fn () => route('admin.alm.salidas.edit', 1))->toThrow(Exception::class)
            ->and(fn () => route('admin.alm.salidas.destroy', 1))->toThrow(Exception::class);
    });

    it('el listado solo muestra los almacenes que el usuario ve', function () {
        $suyo = Almacen::factory()->create();
        $ajeno = Almacen::factory()->create();

        Salida::factory()->de($suyo)->create();
        Salida::factory()->de($ajeno)->create();

        $usuario = User::factory()->create();
        Permission::firstOrCreate(['name' => 'alm.salidas.ver', 'guard_name' => 'web']);
        $usuario->givePermissionTo('alm.salidas.ver');
        $suyo->usuarios()->attach($usuario->getKey());

        $this->actingAs($usuario)
            ->get(route('admin.alm.salidas.index'))
            ->assertInertia(fn ($page) => $page->has('salidas.data', 1));
    });
});

/**
 * El almacen registra lo que ya paso. Una salida con fecha de manana no es un
 * plan: es material que todavia esta en el anaquel descontado del kardex, y el
 * conteo fisico del dia no cuadraria contra el sistema.
 */
describe('la fecha no puede ser de manana', function () {
    it('rechaza la salida fechada en el futuro', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 50);

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad' => 5],
            ], ['fecha' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('fecha');

        expect(Salida::count())->toBe(0);
    });

    it('acepta hoy y acepta lo capturado con retraso', function (string $fecha) {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 50);

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad' => 5],
            ], ['fecha' => $fecha]))
            ->assertSessionHasNoErrors();
    })->with([
        'hoy' => fn (): string => now()->toDateString(),
        'ayer' => fn (): string => now()->subDay()->toDateString(),
        'la semana pasada' => fn (): string => now()->subWeek()->toDateString(),
    ]);
});

/**
 * En planta el material no se va a otro domicilio: se consume aqui mismo. Si
 * ademas no hay pedido que diga quien lo pidio, el departamento es lo unico que
 * dice a quien cargarle el consumo.
 */
describe('la salida directa de planta exige departamento', function () {
    it('rechaza la salida directa de un almacen central sin departamento', function () {
        $almacen = Almacen::factory()->create(['obra_id' => null]);
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 50);

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad' => 5],
            ], ['departamento_id' => null]))
            ->assertSessionHasErrors('departamento_id');

        expect(Salida::count())->toBe(0);
    });

    it('la acepta con departamento', function () {
        $almacen = Almacen::factory()->create(['obra_id' => null]);
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 50);
        $departamento = Departamento::factory()->create();

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad' => 5],
            ], ['departamento_id' => $departamento->id]))
            ->assertSessionHasNoErrors();

        expect(Salida::firstOrFail()->departamento_id)->toBe($departamento->id);
    });

    /** El almacen de obra ya dice a donde va el material: es la obra. */
    it('no la exige en un almacen de obra', function () {
        $almacen = Almacen::factory()->create(['obra_id' => Obra::factory()]);
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 50);

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad' => 5],
            ], ['departamento_id' => null]))
            ->assertSessionHasNoErrors();
    });

    /** Con pedido, quien pide ya quedo asentado ahi. */
    it('no la exige cuando la salida surte un pedido', function () {
        $almacen = Almacen::factory()->create(['obra_id' => null]);
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 50);

        $pedido = Pedido::factory()->de($almacen)->create();
        $renglon = $pedido->detalles()->create([
            'producto_id' => $producto->id,
            'cantidad_solicitada' => 10,
        ]);

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad' => 5, 'pedido_detalle_id' => $renglon->id],
            ], ['pedido_id' => $pedido->id, 'departamento_id' => null]))
            ->assertSessionHasNoErrors();
    });
});

/**
 * El pedido ya contesto a quien se le carga y quien se lo lleva; la salida lo
 * hereda en vez de volver a preguntarlo, y queda escrito en el documento para
 * que el vale impreso no tenga que ir a leer el pedido.
 */
describe('la salida hereda el destino del pedido', function () {
    it('guarda el departamento y el modulo que traia el pedido', function () {
        $almacen = Almacen::factory()->create(['obra_id' => null]);
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 50);

        $departamento = Departamento::factory()->create();
        $modulo = GrupoTrabajo::factory()->create();

        $pedido = Pedido::factory()->de($almacen)->create([
            'departamento_id' => $departamento->id,
            'grupo_trabajo_id' => $modulo->id,
            'recibe_nombre' => 'A. Perez',
        ]);
        $renglon = $pedido->detalles()->create([
            'producto_id' => $producto->id,
            'cantidad_solicitada' => 10,
        ]);

        $this->actingAs(usuarioDeSalidas())
            ->post(route('admin.alm.salidas.store'), salidaValida($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad' => 5, 'pedido_detalle_id' => $renglon->id],
            ], [
                'pedido_id' => $pedido->id,
                'departamento_id' => $departamento->id,
                'grupo_trabajo_id' => $modulo->id,
                'recibe_nombre' => 'A. Perez',
            ]))
            ->assertSessionHasNoErrors();

        $salida = Salida::firstOrFail();

        expect($salida->departamento_id)->toBe($departamento->id)
            ->and($salida->grupo_trabajo_id)->toBe($modulo->id)
            ->and($salida->recibe_nombre)->toBe('A. Perez');
    });

    /** La pantalla necesita los ids, no solo la etiqueta, para precargarlos. */
    it('el listado de pedidos surtibles manda los ids del destino', function () {
        $almacen = Almacen::factory()->create(['obra_id' => null]);
        $producto = Producto::factory()->create();
        sembrar($almacen, $producto, 50);

        $departamento = Departamento::factory()->create();
        $modulo = GrupoTrabajo::factory()->create();

        $pedido = Pedido::factory()->de($almacen)->create([
            'departamento_id' => $departamento->id,
            'grupo_trabajo_id' => $modulo->id,
        ]);
        $pedido->detalles()->create(['articulo_id' => articuloDe($producto), 'cantidad_solicitada' => 10]);

        $this->actingAs(usuarioDeSalidas())
            ->get(route('admin.alm.salidas.create', ['almacen_id' => $almacen->id]))
            ->assertInertia(fn ($page) => $page
                ->where('pedidosSurtibles.0.departamento_id', $departamento->id)
                ->where('pedidosSurtibles.0.grupo_trabajo_id', $modulo->id)
            );
    });
});
