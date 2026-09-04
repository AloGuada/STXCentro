<?php

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Ajuste;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Costos\Producto;
use App\Models\User;
use App\Services\Alm\AlmacenLedger;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>  $permisos
 */
function usuarioDeAjustes(array $permisos = ['ver', 'crear']): User
{
    $user = User::factory()->create();

    $nombres = array_map(fn (string $accion): string => "alm.ajustes.{$accion}", $permisos);
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
function ajusteValido(Almacen $almacen, array $detalles, string $motivo = 'carga_inicial'): array
{
    return [
        'almacen_id' => $almacen->id,
        'motivo' => $motivo,
        'fecha' => now()->toDateString(),
        'observaciones' => 'Conteo del cierre de mes',
        'detalles' => $detalles,
    ];
}

describe('siembra el kardex', function () {
    it('un ajuste sobre un almacen vacio crea la existencia', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs(usuarioDeAjustes())
            ->post(route('admin.alm.ajustes.store'), ajusteValido($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad_contada' => 100, 'costo_unitario' => 4.35],
            ]))
            ->assertRedirect();

        $existencia = Existencia::firstOrFail();
        $movimiento = Movimiento::firstOrFail();

        expect((float) $existencia->cantidad)->toBe(100.0)
            ->and((float) $existencia->costo_promedio)->toBe(4.35)
            ->and($movimiento->tipo)->toBe(MovimientoTipo::Ajuste)
            ->and((float) $movimiento->saldo_antes)->toBe(0.0)
            ->and((float) $movimiento->saldo_despues)->toBe(100.0)
            ->and($movimiento->referencia)->toBe(Ajuste::firstOrFail()->folio);
    });

    it('graba la diferencia, no lo contado', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        app(AlmacenLedger::class)->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 10
        );

        $this->actingAs(usuarioDeAjustes())
            ->post(route('admin.alm.ajustes.store'), ajusteValido($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad_contada' => 96],
            ], 'merma'));

        $detalle = Ajuste::firstOrFail()->detalles()->firstOrFail();

        expect((float) $detalle->cantidad_sistema)->toBe(100.0)
            ->and((float) $detalle->cantidad_contada)->toBe(96.0)
            ->and((float) $detalle->diferencia)->toBe(-4.0)
            // Lo que fue al kardex es la diferencia, no el conteo.
            ->and((float) Movimiento::where('tipo', MovimientoTipo::Ajuste)->firstOrFail()->cantidad)->toBe(-4.0)
            ->and((float) Existencia::firstOrFail()->cantidad)->toBe(96.0);
    });

    it('el ajuste puede dejar el saldo en negativo', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        app(AlmacenLedger::class)->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Entrada, 10, 5
        );

        // Un almacenista que cuenta cero donde el sistema dice diez no está
        // equivocado: está reportando lo que hay, y el saldo tiene que poder
        // decirlo aunque el sistema haya prometido más.
        app(AlmacenLedger::class)->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Salida, -10
        );

        $this->actingAs(usuarioDeAjustes())
            ->post(route('admin.alm.ajustes.store'), ajusteValido($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad_contada' => 0],
            ]))
            ->assertRedirect();

        expect((float) Existencia::firstOrFail()->cantidad)->toBe(0.0);
    });

    it('un renglon contado exacto se guarda pero no ensucia el kardex', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        app(AlmacenLedger::class)->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Entrada, 50, 8
        );

        $this->actingAs(usuarioDeAjustes())
            ->post(route('admin.alm.ajustes.store'), ajusteValido($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad_contada' => 50],
            ]));

        expect(Ajuste::firstOrFail()->detalles()->count())->toBe(1)
            // El kardex sólo lista lo que cambia el saldo: la entrada y nada más.
            ->and(Movimiento::count())->toBe(1)
            ->and(Movimiento::where('tipo', MovimientoTipo::Ajuste)->count())->toBe(0);
    });

    it('no deja contar lo que Almacen no guarda', function () {
        $almacen = Almacen::factory()->create();

        // Un flete se compra pero no se guarda, asi que nadie le abrio
        // articulo. Antes se sabia por una bandera; ahora se sabe porque no
        // esta en el catalogo de Almacen. Y sobre todo, no debe estrenar una
        // existencia solo por aparecer en la hoja.
        $this->actingAs(usuarioDeAjustes())
            ->post(route('admin.alm.ajustes.store'), ajusteValido($almacen, [
                ['articulo_id' => 999999, 'cantidad_contada' => 3],
            ]))
            ->assertSessionHasErrors('detalles.0.articulo_id');

        expect(Ajuste::count())->toBe(0)
            ->and(Movimiento::count())->toBe(0)
            ->and(Existencia::count())->toBe(0);
    });

    it('el saldo del sistema se lee al guardar, no al abrir la pantalla', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        // Entre que el almacenista abrió la hoja y la guardó entró material. Si
        // el ajuste guardara el saldo que vio al abrir, inventaría una
        // diferencia que no existe.
        app(AlmacenLedger::class)->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Entrada, 40, 10
        );

        $this->actingAs(usuarioDeAjustes())
            ->post(route('admin.alm.ajustes.store'), ajusteValido($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad_contada' => 40],
            ]));

        expect((float) Ajuste::firstOrFail()->detalles()->firstOrFail()->cantidad_sistema)->toBe(40.0);
    });
});

describe('validacion', function () {
    it('exige al menos un renglon', function () {
        $almacen = Almacen::factory()->create();

        $this->actingAs(usuarioDeAjustes())
            ->post(route('admin.alm.ajustes.store'), ajusteValido($almacen, []))
            ->assertSessionHasErrors('detalles');
    });

    it('no acepta contar en negativo', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs(usuarioDeAjustes())
            ->post(route('admin.alm.ajustes.store'), ajusteValido($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad_contada' => -5],
            ]))
            ->assertSessionHasErrors('detalles.0.cantidad_contada');
    });

    it('no acepta el mismo articulo dos veces', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        // El segundo renglón se mediría contra el saldo que dejó el primero.
        $this->actingAs(usuarioDeAjustes())
            ->post(route('admin.alm.ajustes.store'), ajusteValido($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad_contada' => 10],
                ['articulo_id' => articuloDe($producto), 'cantidad_contada' => 20],
            ]))
            ->assertSessionHasErrors('detalles');
    });

    it('no deja teclear a mano el motivo que genera un conteo', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs(usuarioDeAjustes())
            ->post(route('admin.alm.ajustes.store'), ajusteValido($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad_contada' => 10],
            ], 'conteo_fisico'))
            ->assertSessionHasErrors('motivo');
    });
});

describe('la hoja del conteo ofrece el catalogo, no solo lo que ya hay', function () {
    it('un almacen sin un solo movimiento igual tiene que ofrecer que contar', function () {
        $almacen = Almacen::factory()->create();
        $articulo = Articulo::factory()->create(['activo' => true]);

        // Antes salia vacio —se ofrecian solo los renglones de `alm_existencias`—
        // y un almacen recien abierto no tenia ni un articulo en la lista, asi
        // que su carga inicial no se podia capturar por ningun lado.
        $this->actingAs(usuarioDeAjustes())
            ->getJson(route('admin.alm.almacenes.catalogo-conteo', $almacen))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.articulo_id', $articulo->id)
            ->assertJsonPath('0.cantidad', 0)
            ->assertJsonPath('0.en_el_almacen', false);
    });

    it('trae el saldo del almacen que se pregunta, no el de otro', function () {
        $aqui = Almacen::factory()->create();
        $alla = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        app(AlmacenLedger::class)->registrarPorProducto(
            $alla->id, $producto->id, MovimientoTipo::Entrada, 40, 3
        );

        $this->actingAs(usuarioDeAjustes())
            ->getJson(route('admin.alm.almacenes.catalogo-conteo', $aqui))
            ->assertOk()
            ->assertJsonPath('0.cantidad', 0)
            ->assertJsonPath('0.en_el_almacen', false);

        $this->actingAs(usuarioDeAjustes())
            ->getJson(route('admin.alm.almacenes.catalogo-conteo', $alla))
            ->assertOk()
            ->assertJsonPath('0.cantidad', 40)
            ->assertJsonPath('0.en_el_almacen', true);
    });

    it('deja fuera el articulo dado de baja', function () {
        $almacen = Almacen::factory()->create();
        Articulo::factory()->create(['activo' => false]);

        $this->actingAs(usuarioDeAjustes())
            ->getJson(route('admin.alm.almacenes.catalogo-conteo', $almacen))
            ->assertOk()
            ->assertJsonCount(0);
    });

    it('contar un articulo que el almacen no tenia le abre la existencia', function () {
        $almacen = Almacen::factory()->create();
        $articulo = Articulo::factory()->create();

        $this->actingAs(usuarioDeAjustes())
            ->post(route('admin.alm.ajustes.store'), ajusteValido($almacen, [
                ['articulo_id' => $articulo->id, 'cantidad_contada' => 12, 'costo_unitario' => 8.5],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $existencia = Existencia::firstOrFail();

        expect((int) $existencia->almacen_id)->toBe($almacen->id)
            ->and((int) $existencia->articulo_id)->toBe($articulo->id)
            ->and((float) $existencia->cantidad)->toBe(12.0)
            ->and((float) $existencia->costo_promedio)->toBe(8.5);
    });

    it('la hoja del conteo se cierra para un almacen que no le toca', function () {
        $ajeno = Almacen::factory()->create();

        $usuario = User::factory()->create();
        Permission::firstOrCreate(['name' => 'alm.ajustes.crear', 'guard_name' => 'web']);
        $usuario->givePermissionTo('alm.ajustes.crear');

        $this->actingAs($usuario)
            ->getJson(route('admin.alm.almacenes.catalogo-conteo', $ajeno))
            ->assertForbidden();
    });
});

describe('el articulo se valida contra el catalogo de Almacen', function () {
    it('acepta un articulo que no tiene producto de Compras detras', function () {
        $almacen = Almacen::factory()->create();

        // La carga inicial de un almacen abre articulos sin `producto_id`: es
        // material que existe en la bodega y todavia no se empareja con nada de
        // Compras. Contarlo tiene que poder hacerse.
        $articulo = Articulo::factory()->create(['producto_id' => null]);

        $this->actingAs(usuarioDeAjustes())
            ->post(route('admin.alm.ajustes.store'), ajusteValido($almacen, [
                ['articulo_id' => $articulo->id, 'cantidad_contada' => 7],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect((float) Existencia::firstOrFail()->cantidad)->toBe(7.0);
    });

    it('no le pregunta al producto que comparte el id del articulo', function () {
        $almacen = Almacen::factory()->create();
        $articulo = Articulo::factory()->create(['producto_id' => null]);

        // Los dos catalogos numeran por su cuenta, asi que el id de un articulo
        // cae sobre un producto ajeno. Cuando ese vecino no controlaba
        // inventario, la validacion rechazaba un articulo que si existe.
        Producto::factory()->create([
            'id' => $articulo->id,
            'controla_inventario' => false,
        ]);

        $this->actingAs(usuarioDeAjustes())
            ->post(route('admin.alm.ajustes.store'), ajusteValido($almacen, [
                ['articulo_id' => $articulo->id, 'cantidad_contada' => 3],
            ]))
            ->assertSessionHasNoErrors();
    });

    it('rechaza un id que no es de ningun articulo', function () {
        $almacen = Almacen::factory()->create();

        $this->actingAs(usuarioDeAjustes())
            ->post(route('admin.alm.ajustes.store'), ajusteValido($almacen, [
                ['articulo_id' => 999999, 'cantidad_contada' => 1],
            ]))
            ->assertSessionHasErrors('detalles.0.articulo_id');
    });
});

describe('inmutabilidad y permisos', function () {
    it('no expone rutas para editar ni borrar un ajuste', function () {
        expect(fn () => route('admin.alm.ajustes.edit', 1))->toThrow(Exception::class)
            ->and(fn () => route('admin.alm.ajustes.update', 1))->toThrow(Exception::class)
            ->and(fn () => route('admin.alm.ajustes.destroy', 1))->toThrow(Exception::class);
    });

    it('ver ajustes no alcanza para capturarlos', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        $usuario = usuarioDeAjustes(['ver']);

        $this->actingAs($usuario)->get(route('admin.alm.ajustes.index'))->assertOk();
        $this->actingAs($usuario)->get(route('admin.alm.ajustes.create'))->assertForbidden();
        $this->actingAs($usuario)
            ->post(route('admin.alm.ajustes.store'), ajusteValido($almacen, [
                ['articulo_id' => articuloDe($producto), 'cantidad_contada' => 10],
            ]))
            ->assertForbidden();
    });

    it('el listado solo muestra los almacenes que el usuario ve', function () {
        $suyo = Almacen::factory()->create();
        $ajeno = Almacen::factory()->create();

        Ajuste::factory()->de($suyo)->create();
        Ajuste::factory()->de($ajeno)->create();

        $usuario = User::factory()->create();
        Permission::firstOrCreate(['name' => 'alm.ajustes.ver', 'guard_name' => 'web']);
        $usuario->givePermissionTo('alm.ajustes.ver');
        $suyo->usuarios()->attach($usuario->getKey());

        $this->actingAs($usuario)
            ->get(route('admin.alm.ajustes.index'))
            ->assertInertia(fn ($page) => $page->has('ajustes.data', 1));
    });

    it('la ficha se cierra para un almacen que no le toca', function () {
        $ajeno = Almacen::factory()->create();
        $ajuste = Ajuste::factory()->de($ajeno)->create();

        $usuario = User::factory()->create();
        Permission::firstOrCreate(['name' => 'alm.ajustes.ver', 'guard_name' => 'web']);
        $usuario->givePermissionTo('alm.ajustes.ver');

        $this->actingAs($usuario)
            ->get(route('admin.alm.ajustes.show', $ajuste))
            ->assertForbidden();
    });
});
