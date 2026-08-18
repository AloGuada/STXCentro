<?php

use App\Enums\Alm\MovimientoTipo;
use App\Enums\Alm\PedidoEstatus;
use App\Enums\Alm\TransferenciaEstatus;
use App\Models\Alm\Almacen;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Alm\Pedido;
use App\Models\Alm\Transferencia;
use App\Models\Costos\Producto;
use App\Models\User;
use App\Models\Usuario;
use App\Services\Alm\AlmacenLedger;
use App\Services\Alm\SaldoEnTransito;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>  $permisos
 */
function usuarioDeTransferencias(array $permisos = ['ver', 'enviar', 'recibir']): User
{
    $user = User::factory()->create();

    $nombres = array_map(fn (string $a): string => "alm.transferencias.{$a}", $permisos);
    $nombres[] = 'alm.almacenes.ver-todos';

    foreach ($nombres as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($nombres);

    return $user;
}

function sembrarEn(Almacen $almacen, Producto $producto, float $cantidad, float $costo = 10): void
{
    app(AlmacenLedger::class)->registrarPorProducto(
        $almacen->id, $producto->id, MovimientoTipo::Entrada, $cantidad, $costo
    );
}

/**
 * @param  list<array<string, mixed>>  $detalles
 * @return array<string, mixed>
 */
function envioValido(Almacen $origen, Almacen $destino, array $detalles, array $extra = []): array
{
    return [
        'almacen_origen_id' => $origen->id,
        'almacen_destino_id' => $destino->id,
        'fecha_envio' => now()->toDateString(),
        'observaciones' => 'Va en la Ranger',
        'detalles' => $detalles,
        ...$extra,
    ];
}

describe('el primer tiempo', function () {
    it('descarga el origen y deja el material sin dueño', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrarEn($origen, $producto, 100, 5);

        $this->actingAs(usuarioDeTransferencias())
            ->post(route('admin.alm.transferencias.store'), envioValido($origen, $destino, [
                ['producto_id' => $producto->id, 'cantidad_enviada' => 60],
            ]))
            ->assertRedirect();

        $transferencia = Transferencia::firstOrFail();

        expect($transferencia->estatus)->toBe(TransferenciaEstatus::EnTransito)
            ->and($transferencia->folio)->toStartWith('TRA-')
            // Un solo movimiento: el destino todavía no carga nada.
            ->and(Movimiento::where('tipo', MovimientoTipo::TransferenciaSalida)->count())->toBe(1)
            ->and(Movimiento::where('tipo', MovimientoTipo::TransferenciaEntrada)->count())->toBe(0)
            ->and(Existencia::where('almacen_id', $destino->id)->count())->toBe(0);
    });

    /**
     * El assert que da sentido a los dos tiempos: entre el envío y la recepción
     * el material no es existencia de nadie, así que la suma de los saldos
     * **no** cuadra con lo que hay en la empresa.
     */
    it('el saldo total no cuadra mientras va en el camion', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrarEn($origen, $producto, 100, 5);

        $this->actingAs(usuarioDeTransferencias())
            ->post(route('admin.alm.transferencias.store'), envioValido($origen, $destino, [
                ['producto_id' => $producto->id, 'cantidad_enviada' => 60],
            ]));

        $enExistencias = (float) Existencia::sum('cantidad');
        $enTransito = app(SaldoEnTransito::class)->porProducto($producto->id);

        expect($enExistencias)->toBe(40.0)
            ->and($enTransito)->toBe(60.0)
            // Sólo sumando el tránsito vuelve a dar el total de la empresa.
            ->and($enExistencias + $enTransito)->toBe(100.0);
    });

    it('no deja transferir a si mismo ni enviar mas de lo que hay', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrarEn($origen, $producto, 10);

        $usuario = usuarioDeTransferencias();

        $this->actingAs($usuario)
            ->post(route('admin.alm.transferencias.store'), envioValido($origen, $origen, [
                ['producto_id' => $producto->id, 'cantidad_enviada' => 5],
            ]))
            ->assertSessionHasErrors('almacen_destino_id');

        $this->actingAs($usuario)
            ->post(route('admin.alm.transferencias.store'), envioValido($origen, $destino, [
                ['producto_id' => $producto->id, 'cantidad_enviada' => 11],
            ]))
            ->assertSessionHasErrors('detalles.0.cantidad_enviada');

        expect(Transferencia::count())->toBe(0);
    });
});

describe('el segundo tiempo', function () {
    it('carga el destino por lo confirmado y cierra el transito', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrarEn($origen, $producto, 100, 5);

        $usuario = usuarioDeTransferencias();

        $this->actingAs($usuario)->post(route('admin.alm.transferencias.store'), envioValido($origen, $destino, [
            ['producto_id' => $producto->id, 'cantidad_enviada' => 60],
        ]));

        $transferencia = Transferencia::with('detalles')->firstOrFail();
        $renglon = $transferencia->detalles->first();

        $this->actingAs($usuario)
            ->patch(route('admin.alm.transferencias.recibir', $transferencia), [
                'recibido' => [$renglon->id => 60],
            ])
            ->assertRedirect();

        expect($transferencia->refresh()->estatus)->toBe(TransferenciaEstatus::Recibida)
            ->and((float) Existencia::where('almacen_id', $destino->id)->value('cantidad'))->toBe(60.0)
            // Cerrado el documento, el tránsito baja a cero solo: la consulta
            // sólo mira las abiertas.
            ->and(app(SaldoEnTransito::class)->porProducto($producto->id))->toBe(0.0)
            ->and((float) Existencia::sum('cantidad'))->toBe(100.0);
    });

    it('el material llega al costo con el que salio, no al del destino', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        sembrarEn($origen, $producto, 100, 8);
        // El destino ya tiene del mismo artículo, comprado más caro.
        sembrarEn($destino, $producto, 100, 20);

        $usuario = usuarioDeTransferencias();

        $this->actingAs($usuario)->post(route('admin.alm.transferencias.store'), envioValido($origen, $destino, [
            ['producto_id' => $producto->id, 'cantidad_enviada' => 100],
        ]));

        $transferencia = Transferencia::with('detalles')->firstOrFail();
        $renglon = $transferencia->detalles->first();

        $this->actingAs($usuario)->patch(route('admin.alm.transferencias.recibir', $transferencia), [
            'recibido' => [$renglon->id => 100],
        ]);

        // (100*20 + 100*8) / 200 = 14. Si el destino usara su propio promedio,
        // mover material entre bodegas inventaría valor.
        expect((float) Existencia::where('almacen_id', $destino->id)->value('costo_promedio'))->toBe(14.0)
            ->and((float) Existencia::where('almacen_id', $origen->id)->value('valor'))->toBe(0.0);
    });

    it('recibir de menos graba el faltante con dueno', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrarEn($origen, $producto, 100, 5);

        $usuario = usuarioDeTransferencias();
        $culpable = Usuario::factory()->create();

        $this->actingAs($usuario)->post(route('admin.alm.transferencias.store'), envioValido($origen, $destino, [
            ['producto_id' => $producto->id, 'cantidad_enviada' => 80],
        ]));

        $transferencia = Transferencia::with('detalles')->firstOrFail();
        $renglon = $transferencia->detalles->first();

        $this->actingAs($usuario)
            ->patch(route('admin.alm.transferencias.recibir', $transferencia), [
                'recibido' => [$renglon->id => 74.25],
                'faltante_responsable_id' => $culpable->id,
            ])
            ->assertRedirect();

        $transferencia->refresh()->load('detalles');

        expect((float) Existencia::where('almacen_id', $destino->id)->value('cantidad'))->toBe(74.25)
            ->and($transferencia->faltante_responsable_id)->toBe($culpable->id)
            ->and($transferencia->resumen()['faltante'])->toBe(5.75)
            // La diferencia ya desapareció en el movimiento del origen: no hay
            // un tercer asiento que la explique.
            ->and(Movimiento::count())->toBe(3);
    });

    it('el faltante no se cierra sin dueno', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrarEn($origen, $producto, 100, 5);

        $usuario = usuarioDeTransferencias();

        $this->actingAs($usuario)->post(route('admin.alm.transferencias.store'), envioValido($origen, $destino, [
            ['producto_id' => $producto->id, 'cantidad_enviada' => 80],
        ]));

        $transferencia = Transferencia::with('detalles')->firstOrFail();

        // Para eso son los dos tiempos: si nadie responde, el faltante se
        // vuelve un descuadre anónimo.
        $this->actingAs($usuario)
            ->patch(route('admin.alm.transferencias.recibir', $transferencia), [
                'recibido' => [$transferencia->detalles->first()->id => 70],
            ])
            ->assertSessionHasErrors('faltante_responsable_id');

        expect($transferencia->refresh()->estatus)->toBe(TransferenciaEstatus::EnTransito);
    });

    it('no acepta recibir mas de lo que se envio', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrarEn($origen, $producto, 100, 5);

        $usuario = usuarioDeTransferencias();

        $this->actingAs($usuario)->post(route('admin.alm.transferencias.store'), envioValido($origen, $destino, [
            ['producto_id' => $producto->id, 'cantidad_enviada' => 50],
        ]));

        $transferencia = Transferencia::with('detalles')->firstOrFail();

        // El sobrante se corrige con un ajuste en el destino, no ampliando el
        // envío hacia atrás: eso falsearía el kardex del origen.
        $this->actingAs($usuario)
            ->patch(route('admin.alm.transferencias.recibir', $transferencia), [
                'recibido' => [$transferencia->detalles->first()->id => 55],
            ])
            ->assertSessionHasErrors('recibido');
    });

    it('no se puede recibir dos veces', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrarEn($origen, $producto, 100, 5);

        $usuario = usuarioDeTransferencias();

        $this->actingAs($usuario)->post(route('admin.alm.transferencias.store'), envioValido($origen, $destino, [
            ['producto_id' => $producto->id, 'cantidad_enviada' => 50],
        ]));

        $transferencia = Transferencia::with('detalles')->firstOrFail();
        $renglon = $transferencia->detalles->first();

        $this->actingAs($usuario)->patch(route('admin.alm.transferencias.recibir', $transferencia), [
            'recibido' => [$renglon->id => 50],
        ]);

        $this->actingAs($usuario)
            ->patch(route('admin.alm.transferencias.recibir', $transferencia->refresh()), [
                'recibido' => [$renglon->id => 50],
            ])
            ->assertSessionHasErrors('transferencia');

        expect((float) Existencia::where('almacen_id', $destino->id)->value('cantidad'))->toBe(50.0);
    });
});

describe('cancelacion', function () {
    it('mientras va en el camino devuelve al origen', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrarEn($origen, $producto, 100, 5);

        $usuario = usuarioDeTransferencias();

        $this->actingAs($usuario)->post(route('admin.alm.transferencias.store'), envioValido($origen, $destino, [
            ['producto_id' => $producto->id, 'cantidad_enviada' => 60],
        ]));

        $transferencia = Transferencia::firstOrFail();

        $this->actingAs($usuario)
            ->patch(route('admin.alm.transferencias.cancelar', $transferencia), ['motivo' => 'No salió el camión'])
            ->assertRedirect();

        expect((float) Existencia::where('almacen_id', $origen->id)->value('cantidad'))->toBe(100.0)
            ->and(app(SaldoEnTransito::class)->porProducto($producto->id))->toBe(0.0)
            ->and($transferencia->refresh()->estaCancelada())->toBeTrue();
    });

    it('una vez recibida ya no se cancela', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrarEn($origen, $producto, 100, 5);

        $usuario = usuarioDeTransferencias();

        $this->actingAs($usuario)->post(route('admin.alm.transferencias.store'), envioValido($origen, $destino, [
            ['producto_id' => $producto->id, 'cantidad_enviada' => 60],
        ]));

        $transferencia = Transferencia::with('detalles')->firstOrFail();

        $this->actingAs($usuario)->patch(route('admin.alm.transferencias.recibir', $transferencia), [
            'recibido' => [$transferencia->detalles->first()->id => 60],
        ]);

        // El material ya está allá: devolverlo es otra transferencia.
        $this->actingAs($usuario)
            ->patch(route('admin.alm.transferencias.cancelar', $transferencia->refresh()), ['motivo' => 'ups'])
            ->assertSessionHasErrors('transferencia');
    });
});

describe('surtir un pedido de obra', function () {
    it('descuenta con lo enviado, no con lo confirmado', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrarEn($origen, $producto, 500, 5);

        $pedido = Pedido::factory()->de($origen)->paraObra()->create();
        $renglon = $pedido->detalles()->create([
            'producto_id' => $producto->id,
            'cantidad_solicitada' => 100,
        ]);

        $usuario = usuarioDeTransferencias();

        $this->actingAs($usuario)->post(route('admin.alm.transferencias.store'), envioValido($origen, $destino, [
            ['producto_id' => $producto->id, 'pedido_detalle_id' => $renglon->id, 'cantidad_enviada' => 100],
        ], ['pedido_id' => $pedido->id]));

        // El origen ya cumplió: si contara lo confirmado, el pedido seguiría
        // apareciendo como surtible y se despacharía dos veces.
        expect((float) $renglon->refresh()->cantidad_surtida)->toBe(100.0)
            ->and($pedido->refresh()->estatus)->toBe(PedidoEstatus::Surtido)
            ->and(Pedido::transferibles($origen->id)->count())->toBe(0);

        $transferencia = Transferencia::with('detalles')->firstOrFail();

        $this->actingAs($usuario)->patch(route('admin.alm.transferencias.recibir', $transferencia), [
            'recibido' => [$transferencia->detalles->first()->id => 90],
            'faltante_responsable_id' => Usuario::factory()->create()->id,
        ]);

        // El faltante del traslado tiene su propio dueño y no es deuda del
        // pedido: si la obra necesita reponerlo, levanta uno nuevo.
        expect((float) $renglon->refresh()->cantidad_surtida)->toBe(100.0)
            ->and($pedido->refresh()->estatus)->toBe(PedidoEstatus::Surtido);
    });

    it('un pedido de planta no se surte con transferencia', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrarEn($origen, $producto, 500, 5);

        $pedido = Pedido::factory()->de($origen)->create();
        $renglon = $pedido->detalles()->create([
            'producto_id' => $producto->id,
            'cantidad_solicitada' => 100,
        ]);

        $this->actingAs(usuarioDeTransferencias())
            ->post(route('admin.alm.transferencias.store'), envioValido($origen, $destino, [
                ['producto_id' => $producto->id, 'pedido_detalle_id' => $renglon->id, 'cantidad_enviada' => 100],
            ], ['pedido_id' => $pedido->id]))
            ->assertSessionHasErrors('pedido_id');
    });
});

describe('permisos', function () {
    it('enviar no alcanza para recibir, ni al reves', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrarEn($origen, $producto, 100, 5);

        $this->actingAs(usuarioDeTransferencias())
            ->post(route('admin.alm.transferencias.store'), envioValido($origen, $destino, [
                ['producto_id' => $producto->id, 'cantidad_enviada' => 10],
            ]));

        $transferencia = Transferencia::firstOrFail();

        // Los dos tiempos son de dos personas: si una firma alcanzara para
        // ambos, el faltante lo cerraría quien lo cargó.
        $almacenista = usuarioDeTransferencias(['enviar']);
        $obra = usuarioDeTransferencias(['recibir']);

        $this->actingAs($almacenista)->get(route('admin.alm.transferencias.create'))->assertOk();
        $this->actingAs($almacenista)->get(route('admin.alm.transferencias.show', $transferencia))->assertForbidden();

        $this->actingAs($obra)->get(route('admin.alm.transferencias.show', $transferencia))->assertOk();
        $this->actingAs($obra)->get(route('admin.alm.transferencias.create'))->assertForbidden();
    });

    it('la ve quien puede ver cualquiera de los dos extremos', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        sembrarEn($origen, $producto, 100, 5);

        $this->actingAs(usuarioDeTransferencias())
            ->post(route('admin.alm.transferencias.store'), envioValido($origen, $destino, [
                ['producto_id' => $producto->id, 'cantidad_enviada' => 10],
            ]));

        $transferencia = Transferencia::firstOrFail();

        $deObra = User::factory()->create();
        Permission::firstOrCreate(['name' => 'alm.transferencias.recibir', 'guard_name' => 'web']);
        $deObra->givePermissionTo('alm.transferencias.recibir');
        $destino->usuarios()->attach($deObra->getKey());

        $ajeno = User::factory()->create();
        $ajeno->givePermissionTo('alm.transferencias.recibir');

        $this->actingAs($deObra)->get(route('admin.alm.transferencias.show', $transferencia))->assertOk();
        $this->actingAs($ajeno)->get(route('admin.alm.transferencias.show', $transferencia))->assertForbidden();
    });

    it('no expone rutas para editar ni borrar', function () {
        expect(fn () => route('admin.alm.transferencias.edit', 1))->toThrow(Exception::class)
            ->and(fn () => route('admin.alm.transferencias.destroy', 1))->toThrow(Exception::class);
    });
});
