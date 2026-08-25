<?php

use App\Models\Alm\Almacen;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Costos\Entrega;
use App\Models\Costos\Producto;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>  $permisos
 */
function usuarioDeEntradas(array $permisos = ['ver', 'crear']): User
{
    $user = User::factory()->create();

    $nombres = array_map(fn (string $a): string => "alm.entradas.{$a}", $permisos);
    $nombres[] = 'alm.almacenes.ver-todos';

    foreach ($nombres as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($nombres);

    return $user;
}

/**
 * @param  list<array<string, mixed>>  $detalles
 * @return array<string, mixed>
 */
function entradaValida(Almacen $almacen, array $detalles): array
{
    return [
        'almacen_id' => $almacen->id,
        'fecha_entrega' => now()->toDateString(),
        'observaciones' => 'Llegó sin orden, lo trajo el proveedor',
        'detalles' => $detalles,
    ];
}

describe('la entrada carga el kardex', function () {
    it('escribe en costos_entregas, no en una tabla nueva', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs(usuarioDeEntradas())
            ->post(route('admin.alm.entradas.store'), entradaValida($almacen, [
                ['producto_id' => $producto->id, 'cantidad_recibida' => 100, 'precio_unitario' => 4.35],
            ]))
            ->assertRedirect();

        $entrada = Entrega::firstOrFail();

        expect($entrada->almacen_id)->toBe($almacen->id)
            // Sin orden: el material llegó sin compra de por medio.
            ->and($entrada->orden_compra_id)->toBeNull()
            ->and($entrada->esSinOrden())->toBeTrue()
            // El folio sigue siendo REC: dos prefijos sobre la misma tabla según
            // quién capturó es dolor permanente por un rótulo.
            ->and($entrada->folio)->toStartWith('REC-')
            ->and((float) Existencia::firstOrFail()->cantidad)->toBe(100.0)
            ->and((float) Existencia::firstOrFail()->costo_promedio)->toBe(4.35);
    });

    it('sella el articulo, la descripcion y la unidad en el renglon', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['descripcion' => 'Tornillo A325', 'unidad' => 'PZA']);

        $this->actingAs(usuarioDeEntradas())
            ->post(route('admin.alm.entradas.store'), entradaValida($almacen, [
                ['producto_id' => $producto->id, 'cantidad_recibida' => 10, 'precio_unitario' => 5],
            ]));

        $detalle = Entrega::firstOrFail()->detalles()->firstOrFail();

        // Sin orden no hay de dónde heredarlas, y el renglón tiene que poder
        // imprimirse solo.
        expect($detalle->producto_id)->toBe($producto->id)
            ->and($detalle->descripcion)->toBe('Tornillo A325')
            ->and($detalle->unidad)->toBe('PZA')
            ->and($detalle->orden_compra_detalle_id)->toBeNull();
    });

    it('exige el costo cuando no hay orden de la cual heredarlo', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs(usuarioDeEntradas())
            ->post(route('admin.alm.entradas.store'), entradaValida($almacen, [
                ['producto_id' => $producto->id, 'cantidad_recibida' => 10],
            ]))
            ->assertSessionHasErrors('detalles.0.precio_unitario');

        expect(Entrega::count())->toBe(0);
    });

    it('no deja recibir lo que no lleva kardex', function () {
        $almacen = Almacen::factory()->create();
        $flete = Producto::factory()->sinInventario()->create();

        $this->actingAs(usuarioDeEntradas())
            ->post(route('admin.alm.entradas.store'), entradaValida($almacen, [
                ['producto_id' => $flete->id, 'cantidad_recibida' => 1, 'precio_unitario' => 8500],
            ]))
            ->assertSessionHasErrors('detalles.0.producto_id');
    });
});

describe('el registrador sobre una recepcion de orden', function () {
    it('no toca el kardex cuando la recepcion no trae almacen', function () {
        // Costos puede seguir capturando recepciones que no pasan por una
        // bodega: ésas no mueven existencia.
        $entrega = Entrega::factory()->create(['almacen_id' => null]);

        expect(app(\App\Services\Alm\RegistradorEntradaAlmacen::class)->aplicar($entrega))->toBe(0)
            ->and(Movimiento::count())->toBe(0)
            ->and(Existencia::count())->toBe(0);
    });

    it('es idempotente: aplicar dos veces no carga dos veces', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs(usuarioDeEntradas())
            ->post(route('admin.alm.entradas.store'), entradaValida($almacen, [
                ['producto_id' => $producto->id, 'cantidad_recibida' => 50, 'precio_unitario' => 10],
            ]));

        $entrada = Entrega::with('detalles')->firstOrFail();

        app(\App\Services\Alm\RegistradorEntradaAlmacen::class)->aplicar($entrada);

        expect((float) Existencia::firstOrFail()->cantidad)->toBe(50.0)
            ->and(Movimiento::count())->toBe(1);
    });

    it('el reverso deja el saldo en negativo si el material ya se consumio', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs(usuarioDeEntradas())
            ->post(route('admin.alm.entradas.store'), entradaValida($almacen, [
                ['producto_id' => $producto->id, 'cantidad_recibida' => 100, 'precio_unitario' => 10],
            ]));

        $entrada = Entrega::with('detalles')->firstOrFail();
        $registrador = app(\App\Services\Alm\RegistradorEntradaAlmacen::class);

        // Se gastó todo antes de que alguien cancelara la recepción.
        app(\App\Services\Alm\AlmacenLedger::class)->registrarPorProducto(
            $almacen->id, $producto->id, \App\Enums\Alm\MovimientoTipo::Salida, -80
        );

        expect($registrador->revertir($entrada, 'Se capturó de más'))->toBe(1);

        // −20 es información correcta: se gastó algo que ahora se dice no haber
        // recibido. Bloquear la cancelación sería un callejón sin salida.
        expect((float) Existencia::firstOrFail()->cantidad)->toBe(-80.0)
            ->and(Movimiento::where('es_reverso', true)->count())->toBe(1);
    });

    it('no revierte dos veces la misma recepcion', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs(usuarioDeEntradas())
            ->post(route('admin.alm.entradas.store'), entradaValida($almacen, [
                ['producto_id' => $producto->id, 'cantidad_recibida' => 100, 'precio_unitario' => 10],
            ]));

        $entrada = Entrega::with('detalles')->firstOrFail();
        $registrador = app(\App\Services\Alm\RegistradorEntradaAlmacen::class);

        $registrador->revertir($entrada, 'x');
        $registrador->revertir($entrada->refresh(), 'otra vez');

        expect((float) Existencia::firstOrFail()->cantidad)->toBe(0.0)
            ->and(Movimiento::where('es_reverso', true)->count())->toBe(1);
    });
});

describe('permisos', function () {
    it('ver entradas no alcanza para capturarlas', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        $usuario = usuarioDeEntradas(['ver']);

        $this->actingAs($usuario)->get(route('admin.alm.entradas.index'))->assertOk();
        $this->actingAs($usuario)->get(route('admin.alm.entradas.create'))->assertForbidden();
        $this->actingAs($usuario)
            ->post(route('admin.alm.entradas.store'), entradaValida($almacen, [
                ['producto_id' => $producto->id, 'cantidad_recibida' => 1, 'precio_unitario' => 1],
            ]))
            ->assertForbidden();
    });

    it('el listado solo muestra los almacenes que el usuario ve', function () {
        $suyo = Almacen::factory()->create();
        $ajeno = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        $capturista = usuarioDeEntradas();
        $this->actingAs($capturista)->post(route('admin.alm.entradas.store'), entradaValida($suyo, [
            ['producto_id' => $producto->id, 'cantidad_recibida' => 1, 'precio_unitario' => 1],
        ]));
        $this->actingAs($capturista)->post(route('admin.alm.entradas.store'), entradaValida($ajeno, [
            ['producto_id' => $producto->id, 'cantidad_recibida' => 1, 'precio_unitario' => 1],
        ]));

        $usuario = User::factory()->create();
        Permission::firstOrCreate(['name' => 'alm.entradas.ver', 'guard_name' => 'web']);
        $usuario->givePermissionTo('alm.entradas.ver');
        $suyo->usuarios()->attach($usuario->getKey());

        $this->actingAs($usuario)
            ->get(route('admin.alm.entradas.index'))
            ->assertInertia(fn ($page) => $page->has('entradas.data', 1));
    });

    it('no expone rutas para editar ni borrar desde almacen', function () {
        expect(fn () => route('admin.alm.entradas.edit', 1))->toThrow(Exception::class)
            ->and(fn () => route('admin.alm.entradas.destroy', 1))->toThrow(Exception::class);
    });
});

/**
 * Igual que la salida: el almacen asienta lo que ya llego. Con fecha de manana
 * el kardex sube por material que nadie ha bajado del camion.
 */
describe('la fecha no puede ser de manana', function () {
    it('rechaza la entrada fechada en el futuro', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        $datos = entradaValida($almacen, [
            ['producto_id' => $producto->id, 'cantidad_recibida' => 10, 'precio_unitario' => 4.35],
        ]);
        $datos['fecha_entrega'] = now()->addDay()->toDateString();

        $this->actingAs(usuarioDeEntradas())
            ->post(route('admin.alm.entradas.store'), $datos)
            ->assertSessionHasErrors('fecha_entrega');

        expect(Entrega::count())->toBe(0);
    });

    it('acepta hoy y acepta lo capturado con retraso', function (string $fecha) {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        $datos = entradaValida($almacen, [
            ['producto_id' => $producto->id, 'cantidad_recibida' => 10, 'precio_unitario' => 4.35],
        ]);
        $datos['fecha_entrega'] = $fecha;

        $this->actingAs(usuarioDeEntradas())
            ->post(route('admin.alm.entradas.store'), $datos)
            ->assertSessionHasNoErrors();
    })->with([
        'hoy' => fn (): string => now()->toDateString(),
        'ayer' => fn (): string => now()->subDay()->toDateString(),
        'la semana pasada' => fn (): string => now()->subWeek()->toDateString(),
    ]);
});
