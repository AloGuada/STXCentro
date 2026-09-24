<?php

use App\Enums\Alm\ActivoEstatus;
use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Activo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Alm\Ubicacion;
use App\Models\User;
use App\Services\Alm\RegistradorPiezas;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>  $permisos
 */
function usuarioDeActivos(array $permisos = ['ver', 'crear', 'editar']): User
{
    $user = User::factory()->create();

    $nombres = array_map(fn (string $accion): string => "alm.activos.{$accion}", $permisos);
    $nombres[] = 'alm.almacenes.ver-todos';
    $nombres[] = 'alm.existencias.ver';

    foreach ($nombres as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($nombres);

    return $user;
}

/**
 * El invariante que hace confiable a Existencias: para un artículo por pieza, el
 * saldo es exactamente el número de piezas vigentes en ese almacén.
 */
function invarianteDePiezas(Almacen $almacen, Articulo $articulo): void
{
    $piezas = Activo::query()
        ->where('almacen_id', $almacen->id)
        ->where('articulo_id', $articulo->id)
        ->vigentes()
        ->count();

    $saldo = (float) Existencia::query()
        ->where('almacen_id', $almacen->id)
        ->where('articulo_id', $articulo->id)
        ->value('cantidad');

    expect($saldo)->toBe((float) $piezas);
}

describe('el alta emite al kardex', function () {
    it('da de alta varias series de un golpe y cada una suma 1', function () {
        $almacen = Almacen::factory()->create();
        $pulidora = Articulo::factory()->porPieza()->create(['descripcion' => 'Pulidora 4 1/2"']);

        $this->actingAs(usuarioDeActivos())
            ->post(route('admin.alm.activos.store'), [
                'articulo_id' => $pulidora->id,
                'almacen_id' => $almacen->id,
                'piezas' => [
                    ['no_serie' => 'PUL-07', 'marca' => 'DeWalt', 'modelo' => 'DWE4120', 'costo' => 2180],
                    ['no_serie' => 'PUL-08', 'marca' => 'DeWalt', 'modelo' => 'DWE4120', 'costo' => 2180],
                    ['no_serie' => 'PUL-11', 'marca' => 'Makita', 'modelo' => 'GA4530', 'costo' => 2340],
                ],
            ])
            ->assertRedirect();

        expect(Activo::count())->toBe(3)
            ->and(Movimiento::where('tipo', MovimientoTipo::Entrada)->count())->toBe(3)
            ->and((float) Existencia::firstOrFail()->cantidad)->toBe(3.0);

        invarianteDePiezas($almacen, $pulidora);
    });

    it('el promedio del renglon es el promedio real de sus piezas', function () {
        $almacen = Almacen::factory()->create();
        $pulidora = Articulo::factory()->porPieza()->create();

        app(RegistradorPiezas::class)->alta($pulidora, $almacen, [
            ['no_serie' => 'A', 'costo' => 2000],
            ['no_serie' => 'B', 'costo' => 3000],
        ]);

        // Con identificación específica, `valor / cantidad` empata con el
        // promedio simple de las piezas. Con promedio móvil divergirían.
        expect((float) Existencia::firstOrFail()->costo_promedio)->toBe(2500.0)
            ->and((float) Existencia::firstOrFail()->valor)->toBe(5000.0);
    });

    it('la marca y el modelo son de la pieza, no del articulo', function () {
        $almacen = Almacen::factory()->create();
        $pulidora = Articulo::factory()->porPieza()->create();

        // La reposición se compró Makita aunque las primeras eran DeWalt.
        app(RegistradorPiezas::class)->alta($pulidora, $almacen, [
            ['no_serie' => 'A', 'marca' => 'DeWalt', 'modelo' => 'DWE4120'],
            ['no_serie' => 'B', 'marca' => 'Makita', 'modelo' => 'GA4530'],
        ]);

        expect(Activo::where('no_serie', 'A')->value('marca'))->toBe('DeWalt')
            ->and(Activo::where('no_serie', 'B')->value('marca'))->toBe('Makita');
    });

    it('el codigo de barras nace de la serie cuando es escaneable', function () {
        $almacen = Almacen::factory()->create();
        $pulidora = Articulo::factory()->porPieza()->create(['codigo' => 'PUL-4120']);

        app(RegistradorPiezas::class)->alta($pulidora, $almacen, [
            ['no_serie' => 'PUL-4120-07'],
            // Un VIN trae caracteres que el lector no toma: se etiqueta con uno
            // nuestro para no depender de lo que grabó el fabricante.
            ['no_serie' => '3FTTW8E9XRA12345#'],
        ]);

        expect(Activo::where('no_serie', 'PUL-4120-07')->value('codigo_barras'))->toBe('PUL-4120-07')
            ->and(Activo::where('no_serie', '3FTTW8E9XRA12345#')->value('codigo_barras'))->toBe('PUL-4120-02');
    });
});

describe('el activo sin serie es un solo renglón por cantidad', function () {
    it('se da de alta por cantidad con un solo asiento y sin padrón de piezas', function () {
        $almacen = Almacen::factory()->create();
        $extension = Articulo::factory()->activoPorCantidad()->create(['descripcion' => 'Extensión 25 m']);

        $this->actingAs(usuarioDeActivos())
            ->post(route('admin.alm.activos.store'), [
                'articulo_id' => $extension->id,
                'almacen_id' => $almacen->id,
                'cantidad' => 12,
                'costo' => 1240,
                'observaciones' => 'Compra de arranque',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $existencia = Existencia::firstOrFail();
        $movimiento = Movimiento::firstOrFail();

        expect(Activo::count())->toBe(0)
            ->and(Movimiento::count())->toBe(1)
            ->and($movimiento->tipo)->toBe(MovimientoTipo::Entrada)
            ->and((float) $movimiento->cantidad)->toBe(12.0)
            ->and((float) $existencia->cantidad)->toBe(12.0)
            ->and((float) $existencia->costo_promedio)->toBe(1240.0)
            ->and($movimiento->observaciones)->toContain('Compra de arranque');
    });

    it('se da de alta con decimales, como los metros de una manguera', function () {
        $almacen = Almacen::factory()->create();
        $manguera = Articulo::factory()->activoPorCantidad()->create(['descripcion' => 'Manguera 1/2"', 'unidad' => 'M']);

        $this->actingAs(usuarioDeActivos())
            ->post(route('admin.alm.activos.store'), [
                'articulo_id' => $manguera->id,
                'almacen_id' => $almacen->id,
                'cantidad' => 30.5,
                'costo' => 45,
            ])
            ->assertSessionHasNoErrors();

        expect((float) Existencia::firstOrFail()->cantidad)->toBe(30.5);
    });

    it('por cantidad no pide series, y por pieza no acepta cantidad', function () {
        $almacen = Almacen::factory()->create();
        $extension = Articulo::factory()->activoPorCantidad()->create();
        $pulidora = Articulo::factory()->porPieza()->create();

        $this->actingAs(usuarioDeActivos())
            ->post(route('admin.alm.activos.store'), [
                'articulo_id' => $extension->id,
                'almacen_id' => $almacen->id,
                'piezas' => [['no_serie' => 'EXT-1']],
            ])
            ->assertSessionHasErrors('cantidad')
            ->assertSessionDoesntHaveErrors('piezas');

        $this->actingAs(usuarioDeActivos())
            ->post(route('admin.alm.activos.store'), [
                'articulo_id' => $pulidora->id,
                'almacen_id' => $almacen->id,
                'cantidad' => 3,
            ])
            ->assertSessionHasErrors('piezas');

        expect(Movimiento::count())->toBe(0)->and(Activo::count())->toBe(0);
    });

    it('la segunda alta se suma al mismo renglón, no abre otro', function () {
        $almacen = Almacen::factory()->create();
        $extension = Articulo::factory()->activoPorCantidad()->create();
        $registrador = app(RegistradorPiezas::class);

        $registrador->altaPorCantidad($extension, $almacen, 10, 100);
        $registrador->altaPorCantidad($extension, $almacen, 5, 130);

        expect(Existencia::count())->toBe(1)
            ->and((float) Existencia::firstOrFail()->cantidad)->toBe(15.0)
            ->and((float) Existencia::firstOrFail()->costo_promedio)->toBe(110.0);
    });

    it('se retiran N contra el renglón y no más de las que hay', function () {
        $almacen = Almacen::factory()->create();
        $extension = Articulo::factory()->activoPorCantidad()->create();
        app(RegistradorPiezas::class)->altaPorCantidad($extension, $almacen, 10, 100);
        $existencia = Existencia::firstOrFail();

        $this->actingAs(usuarioDeActivos())
            ->patch(route('admin.alm.activos.por-cantidad.baja', $existencia), ['cantidad' => 11, 'motivo' => 'x'])
            ->assertSessionHasErrors('cantidad');

        $this->actingAs(usuarioDeActivos())
            ->patch(route('admin.alm.activos.por-cantidad.baja', $existencia), ['cantidad' => 4, 'motivo' => 'Se quemaron'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect((float) $existencia->refresh()->cantidad)->toBe(6.0)
            ->and(Movimiento::where('tipo', MovimientoTipo::Salida)->count())->toBe(1)
            ->and(Movimiento::where('tipo', MovimientoTipo::Salida)->firstOrFail()->observaciones)->toContain('Se quemaron');
    });

    it('la baja por cantidad no aplica a un renglón de insumo ni a uno serializado', function () {
        $almacen = Almacen::factory()->create();
        $tornillo = Articulo::factory()->create();
        $insumo = Existencia::factory()->conSaldo(50)->create([
            'almacen_id' => $almacen->id,
            'articulo_id' => $tornillo->id,
            'producto_id' => $tornillo->producto_id,
        ]);

        $this->actingAs(usuarioDeActivos())
            ->patch(route('admin.alm.activos.por-cantidad.baja', $insumo), ['cantidad' => 1, 'motivo' => 'x'])
            ->assertNotFound();

        expect((float) $insumo->refresh()->cantidad)->toBe(50.0);
    });

    it('el padrón enseña los activos por cantidad junto a las piezas', function () {
        $almacen = Almacen::factory()->create();
        $extension = Articulo::factory()->activoPorCantidad()->create(['descripcion' => 'Extensión 25 m']);
        $pulidora = Articulo::factory()->porPieza()->create();
        $registrador = app(RegistradorPiezas::class);

        $registrador->altaPorCantidad($extension, $almacen, 9, 100);
        $registrador->alta($pulidora, $almacen, [['no_serie' => 'PUL-1']]);

        $this->actingAs(usuarioDeActivos(['ver']))
            ->get(route('admin.alm.activos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/almacen/activos/index')
                ->has('activos.data', 1)
                ->has('porCantidad', 1)
                ->where('porCantidad.0.descripcion', 'Extensión 25 m')
                ->where('porCantidad.0.cantidad', 9)
                ->has('articulos', 2));
    });
});

describe('la baja descarga', function () {
    it('resta 1 al saldo y al costo de esa pieza, no al promedio', function () {
        $almacen = Almacen::factory()->create();
        $pulidora = Articulo::factory()->porPieza()->create();

        [$barata, $cara] = app(RegistradorPiezas::class)->alta($pulidora, $almacen, [
            ['no_serie' => 'A', 'costo' => 2000],
            ['no_serie' => 'B', 'costo' => 3000],
        ]);

        $this->actingAs(usuarioDeActivos())
            ->patch(route('admin.alm.activos.baja', $cara), ['motivo' => 'Se quemó el motor'])
            ->assertRedirect();

        $existencia = Existencia::firstOrFail();

        expect($cara->refresh()->estatus)->toBe(ActivoEstatus::Baja)
            ->and((float) $existencia->cantidad)->toBe(1.0)
            // Queda el valor de la que sigue viva, no el promedio de las dos.
            ->and((float) $existencia->valor)->toBe(2000.0)
            ->and($barata->refresh()->estatus)->toBe(ActivoEstatus::Disponible);

        invarianteDePiezas($almacen, $pulidora);
    });

    it('exige decir por que se retira', function () {
        $almacen = Almacen::factory()->create();
        $pulidora = Articulo::factory()->porPieza()->create();
        [$pieza] = app(RegistradorPiezas::class)->alta($pulidora, $almacen, [['no_serie' => 'A']]);

        $this->actingAs(usuarioDeActivos())
            ->patch(route('admin.alm.activos.baja', $pieza), [])
            ->assertSessionHasErrors('motivo');
    });

    it('dar de baja dos veces no descarga dos veces', function () {
        $almacen = Almacen::factory()->create();
        $pulidora = Articulo::factory()->porPieza()->create();
        [$pieza] = app(RegistradorPiezas::class)->alta($pulidora, $almacen, [['no_serie' => 'A', 'costo' => 100]]);

        $registrador = app(RegistradorPiezas::class);
        $registrador->baja($pieza, 'Se perdió');
        $registrador->baja($pieza->refresh(), 'Se perdió otra vez');

        expect((float) Existencia::firstOrFail()->cantidad)->toBe(0.0)
            ->and(Movimiento::where('tipo', MovimientoTipo::Salida)->count())->toBe(1);
    });
});

describe('mover cambia de almacen sin perder el uno a uno', function () {
    it('deja los dos asientos y la pieza llega sin acomodar', function () {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $rack = Ubicacion::factory()->de($origen)->create();
        $pulidora = Articulo::factory()->porPieza()->create();

        [$pieza] = app(RegistradorPiezas::class)->alta(
            $pulidora, $origen, [['no_serie' => 'A', 'costo' => 1500]], ubicacionId: $rack->id
        );

        app(RegistradorPiezas::class)->mover($pieza, $destino, 'TRA-2608-0001');

        expect($pieza->refresh()->almacen_id)->toBe($destino->id)
            // El «Rack A-1» del origen no existe en el destino.
            ->and($pieza->ubicacion_id)->toBeNull()
            ->and(Movimiento::where('tipo', MovimientoTipo::TransferenciaSalida)->count())->toBe(1)
            ->and(Movimiento::where('tipo', MovimientoTipo::TransferenciaEntrada)->count())->toBe(1);

        invarianteDePiezas($origen, $pulidora);
        invarianteDePiezas($destino, $pulidora);
    });
});

describe('el prestamo no toca el kardex', function () {
    it('prestada y en reparacion siguen pesando en la existencia', function () {
        $almacen = Almacen::factory()->create();
        $pulidora = Articulo::factory()->porPieza()->create();

        $piezas = app(RegistradorPiezas::class)->alta($pulidora, $almacen, [
            ['no_serie' => 'A'], ['no_serie' => 'B'], ['no_serie' => 'C'],
        ]);

        // Cambiar el estatus no mueve saldo: la pulidora prestada sigue siendo
        // del almacén, sólo no está disponible.
        $piezas[0]->update(['estatus' => ActivoEstatus::Prestado]);
        $piezas[1]->update(['estatus' => ActivoEstatus::EnReparacion]);

        expect((float) Existencia::firstOrFail()->cantidad)->toBe(3.0)
            ->and(Movimiento::count())->toBe(3);

        invarianteDePiezas($almacen, $pulidora);
    });

    it('existencias desglosa en que anda cada pieza', function () {
        $almacen = Almacen::factory()->create();
        $pulidora = Articulo::factory()->porPieza()->create();

        $piezas = app(RegistradorPiezas::class)->alta($pulidora, $almacen, [
            ['no_serie' => 'A'], ['no_serie' => 'B'], ['no_serie' => 'C'], ['no_serie' => 'D'],
        ]);

        $piezas[0]->update(['estatus' => ActivoEstatus::Prestado]);
        $piezas[1]->update(['estatus' => ActivoEstatus::Prestado]);
        $piezas[2]->update(['estatus' => ActivoEstatus::EnReparacion]);

        $this->actingAs(usuarioDeActivos())
            ->get(route('admin.alm.existencias.index', ['almacen_id' => $almacen->id]))
            ->assertInertia(fn ($page) => $page
                ->where('existencias.data.0.cantidad', 4)
                // Cuatro pulidoras con tres comprometidas no son cuatro que entregar.
                ->where('existencias.data.0.piezas.disponibles', 1)
                ->where('existencias.data.0.piezas.prestadas', 2)
                ->where('existencias.data.0.piezas.en_reparacion', 1));
    });
});

describe('validacion y permisos', function () {
    it('no deja serializar un articulo que no se controla por pieza', function () {
        $almacen = Almacen::factory()->create();
        $tornillo = Articulo::factory()->create();

        $this->actingAs(usuarioDeActivos())
            ->post(route('admin.alm.activos.store'), [
                'articulo_id' => $tornillo->id,
                'almacen_id' => $almacen->id,
                'piezas' => [['no_serie' => 'X-1']],
            ])
            ->assertSessionHasErrors('articulo_id');

        expect(Activo::count())->toBe(0);
    });

    it('no acepta la misma serie dos veces en el mismo alta', function () {
        $almacen = Almacen::factory()->create();
        $pulidora = Articulo::factory()->porPieza()->create();

        // Pegar una lista de series repite la misma más seguido de lo que parece.
        $this->actingAs(usuarioDeActivos())
            ->post(route('admin.alm.activos.store'), [
                'articulo_id' => $pulidora->id,
                'almacen_id' => $almacen->id,
                'piezas' => [['no_serie' => 'PUL-07'], ['no_serie' => 'PUL-07']],
            ])
            ->assertSessionHasErrors('piezas.1.no_serie');
    });

    it('no acepta una serie que ya existe del mismo articulo', function () {
        $almacen = Almacen::factory()->create();
        $pulidora = Articulo::factory()->porPieza()->create();

        app(RegistradorPiezas::class)->alta($pulidora, $almacen, [['no_serie' => 'PUL-07']]);

        $this->actingAs(usuarioDeActivos())
            ->post(route('admin.alm.activos.store'), [
                'articulo_id' => $pulidora->id,
                'almacen_id' => $almacen->id,
                'piezas' => [['no_serie' => 'PUL-07']],
            ])
            ->assertSessionHasErrors('piezas.0.no_serie');
    });

    it('editar una pieza tampoco acepta una serie que ya usa otra del mismo articulo', function () {
        // Este caso ya funcionaba, pero por accidente: la comprobacion iba por
        // producto y Laravel traduce el null a `IS NULL`, asi que acertaba
        // cuando el choque era del mismo articulo suelto. Se queda porque
        // documenta el comportamiento correcto; el que de verdad protege es el
        // siguiente.
        $almacen = Almacen::factory()->create();
        $pulidora = Articulo::factory()->porPieza()->sinLigar()->create();

        expect($pulidora->producto_id)->toBeNull();

        [$primera, $segunda] = app(RegistradorPiezas::class)->alta($pulidora, $almacen, [
            ['no_serie' => 'PUL-07'],
            ['no_serie' => 'PUL-08'],
        ]);

        $this->actingAs(usuarioDeActivos())
            ->put(route('admin.alm.activos.update', $segunda), [
                'no_serie' => 'PUL-07',
                'estatus' => 'disponible',
            ])
            ->assertSessionHasErrors('no_serie');

        expect($segunda->fresh()->no_serie)->toBe('PUL-08');
    });

    it('editar una pieza si acepta la serie que usa otra de un articulo distinto', function () {
        $almacen = Almacen::factory()->create();
        $registrador = app(RegistradorPiezas::class);

        // Dos articulos sin producto: comparando por producto caian en el mismo
        // saco -ambos `IS NULL`- y esta serie se rechazaba sin razon.
        $registrador->alta(Articulo::factory()->porPieza()->sinLigar()->create(), $almacen, [['no_serie' => '001']]);
        [$pieza] = $registrador->alta(
            Articulo::factory()->porPieza()->sinLigar()->create(),
            $almacen,
            [['no_serie' => '002']],
        );

        $this->actingAs(usuarioDeActivos())
            ->put(route('admin.alm.activos.update', $pieza), [
                'no_serie' => '001',
                'estatus' => 'disponible',
            ])
            ->assertSessionHasNoErrors();

        expect($pieza->fresh()->no_serie)->toBe('001');
    });

    it('la misma serie si puede existir en dos articulos distintos', function () {
        $almacen = Almacen::factory()->create();
        $registrador = app(RegistradorPiezas::class);

        // Dos fabricantes distintos pueden repetir un número.
        $registrador->alta(Articulo::factory()->porPieza()->create(), $almacen, [['no_serie' => '001']]);
        $registrador->alta(Articulo::factory()->porPieza()->create(), $almacen, [['no_serie' => '001']]);

        expect(Activo::where('no_serie', '001')->count())->toBe(2);
    });

    it('la edicion no cambia de almacen ni da de baja', function () {
        $almacen = Almacen::factory()->create();
        $pulidora = Articulo::factory()->porPieza()->create();
        [$pieza] = app(RegistradorPiezas::class)->alta($pulidora, $almacen, [['no_serie' => 'A']]);

        $this->actingAs(usuarioDeActivos())
            ->put(route('admin.alm.activos.update', $pieza), [
                'no_serie' => 'A',
                'estatus' => 'baja',
            ])
            ->assertSessionHasErrors('estatus');

        // El almacén no está entre los campos aceptados: mover de bodega es una
        // transferencia, no una corrección de datos.
        $this->actingAs(usuarioDeActivos())
            ->put(route('admin.alm.activos.update', $pieza), [
                'no_serie' => 'A',
                'estatus' => 'disponible',
                'almacen_id' => Almacen::factory()->create()->id,
            ])
            ->assertSessionHasNoErrors();

        expect($pieza->refresh()->almacen_id)->toBe($almacen->id);
    });

    it('ver el padron no alcanza para dar de alta ni corregir', function () {
        $almacen = Almacen::factory()->create();
        $pulidora = Articulo::factory()->porPieza()->create();
        [$pieza] = app(RegistradorPiezas::class)->alta($pulidora, $almacen, [['no_serie' => 'A']]);

        $usuario = usuarioDeActivos(['ver']);

        $this->actingAs($usuario)->get(route('admin.alm.activos.index'))->assertOk();
        $this->actingAs($usuario)->get(route('admin.alm.activos.create'))->assertForbidden();
        $this->actingAs($usuario)
            ->put(route('admin.alm.activos.update', $pieza), ['no_serie' => 'B', 'estatus' => 'disponible'])
            ->assertForbidden();
        $this->actingAs($usuario)
            ->patch(route('admin.alm.activos.baja', $pieza), ['motivo' => 'x'])
            ->assertForbidden();
    });

    it('el padron solo muestra los almacenes que el usuario ve', function () {
        $suyo = Almacen::factory()->create();
        $ajeno = Almacen::factory()->create();
        $pulidora = Articulo::factory()->porPieza()->create();
        $registrador = app(RegistradorPiezas::class);

        $registrador->alta($pulidora, $suyo, [['no_serie' => 'A']]);
        $registrador->alta($pulidora, $ajeno, [['no_serie' => 'B']]);

        $usuario = User::factory()->create();
        Permission::firstOrCreate(['name' => 'alm.activos.ver', 'guard_name' => 'web']);
        $usuario->givePermissionTo('alm.activos.ver');
        $suyo->usuarios()->attach($usuario->getKey());

        $this->actingAs($usuario)
            ->get(route('admin.alm.activos.index'))
            ->assertInertia(fn ($page) => $page->has('activos.data', 1)
                ->where('activos.data.0.no_serie', 'A'));
    });

    it('no expone una ruta para borrar una pieza', function () {
        expect(fn () => route('admin.alm.activos.destroy', 1))->toThrow(Exception::class);
    });
});
