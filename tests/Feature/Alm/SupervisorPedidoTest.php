<?php

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Pedido;
use App\Models\Alm\Prestamo;
use App\Models\Alm\Salida;
use App\Models\Alm\Transferencia;
use App\Models\Costos\Producto;
use App\Models\Departamento;
use App\Models\User;
use App\Models\Usuario;
use App\Services\Alm\AlmacenLedger;
use App\Services\Alm\RegistradorPiezas;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * El pedido queda a nombre de un supervisor, y ese nombre manda.
 *
 * Quien teclea el pedido no es quien lo pide: lo pide un supervisor —quien
 * tiene `alm.pedidos.supervisar`, por rol o directo— y su nombre es el que
 * firman la salida, el préstamo y la transferencia que surten el pedido, sin
 * que el almacenista pueda cambiarlo.
 */
/**
 * Quien teclea en el almacén. Ve todos los almacenes para que la prueba no
 * dependa de asignarle uno.
 */
function capturista(array $permisos): User
{
    $nombres = [...$permisos, 'alm.almacenes.ver-todos'];

    foreach ($nombres as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($nombres);

    return $usuario;
}

/**
 * @return array<string, mixed>
 */
function pedidoValido(Almacen $almacen, Producto $producto, array $extra = []): array
{
    return [
        'almacen_id' => $almacen->id,
        'departamento_id' => Departamento::factory()->create()->id,
        'fecha' => now()->toDateString(),
        'fecha_requerida' => now()->addDay()->toDateString(),
        'detalles' => [['articulo_id' => articuloDe($producto), 'cantidad_solicitada' => 10]],
        ...$extra,
    ];
}

describe('la lista de supervisores', function () {
    it('son los que tienen el permiso, por rol o directo, y nadie mas', function () {
        Permission::firstOrCreate(['name' => 'alm.pedidos.supervisar', 'guard_name' => 'web']);
        $rol = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $rol->givePermissionTo('alm.pedidos.supervisar');

        $porRol = User::factory()->create(['name' => 'Por rol']);
        $porRol->assignRole('supervisor');
        $directo = supervisorDeAlmacen('Directo');
        $dadoDeBaja = supervisorDeAlmacen('De baja');
        $dadoDeBaja->darDeBaja();
        User::factory()->create(['name' => 'Cualquiera']);

        expect(Usuario::query()->supervisoresDeAlmacen()->pluck('name')->all())
            ->toBe(['Directo', 'Por rol']);
    });

    it('llega a la pantalla del pedido', function () {
        $supervisor = supervisorDeAlmacen('Ana Supervisora');

        $this->actingAs(capturista(['alm.pedidos.crear']))
            ->get(route('admin.alm.pedidos.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/almacen/pedidos/create')
                ->where('supervisores.0.id', $supervisor->id)
                ->where('supervisores.0.name', 'Ana Supervisora'));
    });
});

describe('el pedido', function () {
    it('queda a nombre del supervisor y no de quien lo teclea', function () {
        $supervisor = supervisorDeAlmacen();
        $almacenista = capturista(['alm.pedidos.crear']);

        $this->actingAs($almacenista)
            ->post(route('admin.alm.pedidos.store'), pedidoValido(
                Almacen::factory()->create(),
                Producto::factory()->create(),
                ['solicitante_id' => $supervisor->id],
            ))
            ->assertSessionHasNoErrors();

        expect(Pedido::firstOrFail()->solicitante_id)->toBe($supervisor->id)
            ->and(Pedido::firstOrFail()->solicitante_id)->not->toBe($almacenista->id);
    });

    it('no se guarda sin supervisor ni a nombre de quien no lo es', function () {
        $cualquiera = User::factory()->create();
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs(capturista(['alm.pedidos.crear']))
            ->post(route('admin.alm.pedidos.store'), pedidoValido($almacen, $producto))
            ->assertSessionHasErrors('solicitante_id');

        $this->actingAs(capturista(['alm.pedidos.crear']))
            ->post(route('admin.alm.pedidos.store'), pedidoValido($almacen, $producto, ['solicitante_id' => $cualquiera->id]))
            ->assertSessionHasErrors('solicitante_id');

        expect(Pedido::count())->toBe(0);
    });
});

describe('lo que surte el pedido lleva su nombre', function () {
    it('la salida contra el pedido queda a nombre del supervisor aunque manden otro', function () {
        $supervisor = supervisorDeAlmacen();
        $otro = User::factory()->create();
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        app(AlmacenLedger::class)->registrarPorArticulo(
            almacenId: $almacen->id,
            articuloId: articuloDe($producto),
            tipo: MovimientoTipo::Ajuste,
            cantidad: 100,
            costoUnitario: 10,
        );

        $pedido = Pedido::factory()->de($almacen)->create(['solicitante_id' => $supervisor->id]);
        $renglon = $pedido->detalles()->create(['producto_id' => $producto->id, 'cantidad_solicitada' => 10]);

        $this->actingAs(capturista(['alm.salidas.crear']))
            ->post(route('admin.alm.salidas.store'), [
                'almacen_id' => $almacen->id,
                'pedido_id' => $pedido->id,
                'departamento_id' => $pedido->departamento_id,
                'solicitante_id' => $otro->id,
                'recibe_nombre' => 'A. Pérez',
                'fecha' => now()->toDateString(),
                'detalles' => [['articulo_id' => articuloDe($producto), 'pedido_detalle_id' => $renglon->id, 'cantidad' => 10]],
            ])
            ->assertSessionHasNoErrors();

        expect(Salida::firstOrFail()->solicitante_id)->toBe($supervisor->id);
    });

    it('la transferencia contra el pedido la autoriza el supervisor y no quien la envia', function () {
        $supervisor = supervisorDeAlmacen();
        $origen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        app(AlmacenLedger::class)->registrarPorArticulo(
            almacenId: $origen->id,
            articuloId: articuloDe($producto),
            tipo: MovimientoTipo::Ajuste,
            cantidad: 100,
            costoUnitario: 10,
        );

        $pedido = Pedido::factory()->de($origen)->paraObra()->create(['solicitante_id' => $supervisor->id]);
        $renglon = $pedido->detalles()->create(['producto_id' => $producto->id, 'cantidad_solicitada' => 10]);
        $almacenista = capturista(['alm.transferencias.enviar']);

        $this->actingAs($almacenista)
            ->post(route('admin.alm.transferencias.store'), [
                'almacen_origen_id' => $origen->id,
                'almacen_destino_id' => $pedido->almacen_destino_id,
                'pedido_id' => $pedido->id,
                'fecha_envio' => now()->toDateString(),
                'detalles' => [['articulo_id' => articuloDe($producto), 'pedido_detalle_id' => $renglon->id, 'cantidad_enviada' => 10]],
            ])
            ->assertSessionHasNoErrors();

        $transferencia = Transferencia::firstOrFail();

        expect($transferencia->autorizado_por)->toBe($supervisor->id)
            ->and($transferencia->enviado_por)->toBe($almacenista->id);
    });

    it('el prestamo contra el pedido responde el supervisor aunque manden otro', function () {
        $supervisor = supervisorDeAlmacen();
        $otro = User::factory()->create();
        $almacen = Almacen::factory()->create();
        $extension = Articulo::factory()->activoPorCantidad()->create(['descripcion' => 'Extensión 25 m']);
        app(RegistradorPiezas::class)->altaPorCantidad($extension, $almacen, 10, 100);

        $pedido = Pedido::factory()->de($almacen)->create(['solicitante_id' => $supervisor->id]);
        $renglon = $pedido->detalles()->create([
            'articulo_id' => $extension->id,
            'producto_id' => $extension->producto_id,
            'cantidad_solicitada' => 2,
        ]);

        $this->actingAs(capturista(['alm.prestamos.crear']))
            ->post(route('admin.alm.prestamos.store'), [
                'almacen_id' => $almacen->id,
                'pedido_id' => $pedido->id,
                'responsable_id' => $otro->id,
                'fecha_salida' => today()->toDateString(),
                'renglones' => [['articulo_id' => $extension->id, 'pedido_detalle_id' => $renglon->id, 'cantidad' => 2]],
            ])
            ->assertSessionHasNoErrors();

        expect(Prestamo::firstOrFail()->responsable_id)->toBe($supervisor->id);
    });
});
