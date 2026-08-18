<?php

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Area;
use App\Models\Costos\Producto;
use App\Models\User;
use App\Services\Alm\AlmacenLedger;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>  $permisos
 */
function usuarioDeArticulos(array $permisos = ['ver', 'crear', 'editar']): User
{
    $user = User::factory()->create();

    $nombres = array_map(fn (string $accion): string => "alm.articulos.{$accion}", $permisos);

    foreach ($nombres as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($nombres);

    return $user;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function articuloValido(array $extra = []): array
{
    return [
        'descripcion' => 'Tornillo A325 3/4" x 2"',
        'unidad' => 'PZA',
        'tipo' => 'insumo',
        'clasificacion_abc' => 'A',
        'controla_inventario' => true,
        'se_controla_por_pieza' => false,
        'requiere_verificacion' => false,
        ...$extra,
    ];
}

describe('codigo consecutivo', function () {
    it('lo pone el sistema y no se recibe del formulario', function () {
        $this->actingAs(usuarioDeArticulos())
            ->post(route('admin.alm.articulos.store'), articuloValido(['codigo' => 'YO-LO-PUSE']))
            ->assertRedirect();

        expect(Producto::firstOrFail()->codigo)->toBe('ART-00001');
    });

    it('sigue contando desde el mayor que exista', function () {
        Producto::factory()->create(['codigo' => 'ART-00007']);
        // Los códigos viejos con prefijo propio conviven sin mover el contador.
        Producto::factory()->create(['codigo' => 'TOR-0012']);

        $this->actingAs(usuarioDeArticulos())->post(route('admin.alm.articulos.store'), articuloValido());

        expect(Producto::where('codigo', 'ART-00008')->exists())->toBeTrue();
    });

    /**
     * El bug que ya cobró el folio mensual: comparando como texto, `ART-00099`
     * gana a `ART-00100` y el consecutivo se atasca repitiendo el mismo número.
     */
    it('no se atasca al pasar de tres cifras', function () {
        Producto::factory()->create(['codigo' => 'ART-00099']);
        Producto::factory()->create(['codigo' => 'ART-00100']);

        $this->actingAs(usuarioDeArticulos())->post(route('admin.alm.articulos.store'), articuloValido());

        expect(Producto::where('codigo', 'ART-00101')->exists())->toBeTrue();
    });

    it('el codigo de barras nace igual al codigo', function () {
        $this->actingAs(usuarioDeArticulos())->post(route('admin.alm.articulos.store'), articuloValido());

        $articulo = Producto::firstOrFail();

        expect($articulo->codigo_barras)->toBe($articulo->codigo);
    });

    it('respeta el codigo de barras del fabricante cuando la caja ya lo trae', function () {
        $this->actingAs(usuarioDeArticulos())->post(
            route('admin.alm.articulos.store'),
            articuloValido(['codigo_barras' => '7501234567890']),
        );

        expect(Producto::firstOrFail()->codigo_barras)->toBe('7501234567890');
    });

    it('devolver el codigo de barras vacio lo regresa al del articulo', function () {
        $articulo = Producto::factory()->create([
            'codigo' => 'ART-00042',
            'codigo_barras' => '7501234567890',
        ]);

        $this->actingAs(usuarioDeArticulos())->put(
            route('admin.alm.articulos.update', $articulo),
            articuloValido(['codigo_barras' => null]),
        );

        expect($articulo->refresh()->codigo_barras)->toBe('ART-00042');
    });
});

describe('clasificacion', function () {
    it('no deja seguir un insumo pieza por pieza', function () {
        $this->actingAs(usuarioDeArticulos())
            ->post(route('admin.alm.articulos.store'), articuloValido([
                'tipo' => 'insumo',
                'se_controla_por_pieza' => true,
            ]))
            ->assertSessionHasErrors('se_controla_por_pieza');

        expect(Producto::count())->toBe(0);
    });

    it('acepta seguir un activo pieza por pieza', function () {
        $this->actingAs(usuarioDeArticulos())
            ->post(route('admin.alm.articulos.store'), articuloValido([
                'descripcion' => 'Pulidora 4 1/2" 850W',
                'tipo' => 'activo',
                'se_controla_por_pieza' => true,
                'requiere_verificacion' => true,
            ]))
            ->assertRedirect();

        expect(Producto::firstOrFail()->se_controla_por_pieza)->toBeTrue();
    });

    it('no deja pedir piezas de algo que no lleva kardex', function () {
        $this->actingAs(usuarioDeArticulos())
            ->post(route('admin.alm.articulos.store'), articuloValido([
                'tipo' => 'activo',
                'controla_inventario' => false,
                'se_controla_por_pieza' => true,
            ]))
            ->assertSessionHasErrors('se_controla_por_pieza');
    });

    it('no deja ponerle stock minimo a un servicio', function () {
        $this->actingAs(usuarioDeArticulos())
            ->post(route('admin.alm.articulos.store'), articuloValido([
                'descripcion' => 'Flete foráneo',
                'unidad' => 'SRV',
                'controla_inventario' => false,
                'stock_minimo' => 10,
            ]))
            ->assertSessionHasErrors('stock_minimo');
    });

    it('guarda el area del catalogo', function () {
        $area = Area::factory()->create(['descripcion' => 'Pintura']);

        $this->actingAs(usuarioDeArticulos())->post(
            route('admin.alm.articulos.store'),
            articuloValido(['area_id' => $area->id, 'idsteelex' => 'MAT-000412']),
        );

        $articulo = Producto::firstOrFail();

        expect($articulo->area_id)->toBe($area->id)
            ->and($articulo->idsteelex)->toBe('MAT-000412');
    });
});

describe('listado y ficha', function () {
    it('trae la existencia total sumando todos los almacenes', function () {
        $ledger = app(AlmacenLedger::class);
        $articulo = Producto::factory()->create();

        $ledger->registrarPorProducto(
            Almacen::factory()->create()->id, $articulo->id, MovimientoTipo::Entrada, 100, 10
        );
        $ledger->registrarPorProducto(
            Almacen::factory()->create()->id, $articulo->id, MovimientoTipo::Entrada, 40, 12
        );

        $this->actingAs(usuarioDeArticulos(['ver']))
            ->get(route('admin.alm.articulos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('articulos.data.0.existencia_total', 140));
    });

    it('la ficha desglosa el saldo por almacen', function () {
        $ag = Almacen::factory()->create(['clave' => 'AG']);
        $articulo = Producto::factory()->create();

        app(AlmacenLedger::class)->registrarPorProducto(
            $ag->id, $articulo->id, MovimientoTipo::Entrada, 100, 4.35
        );

        $this->actingAs(usuarioDeArticulos(['ver']))
            ->get(route('admin.alm.articulos.show', $articulo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('existencias', 1)
                ->where('existencias.0.almacen', 'AG')
                ->where('existencias.0.cantidad', 100)
                ->where('existencias.0.costo_promedio', 4.35));
    });

    it('busca por codigo de barras y por el id de Steelex', function () {
        Producto::factory()->create(['descripcion' => 'Guante de carnaza', 'codigo_barras' => '7501234567890']);
        Producto::factory()->create(['descripcion' => 'Electrodo 7018', 'idsteelex' => '7018-125']);
        Producto::factory()->create(['descripcion' => 'Nada que ver']);

        $usuario = usuarioDeArticulos(['ver']);

        $this->actingAs($usuario)
            ->get(route('admin.alm.articulos.index', ['search' => '7501234567890']))
            ->assertInertia(fn ($page) => $page->has('articulos.data', 1)
                ->where('articulos.data.0.descripcion', 'Guante de carnaza'));

        $this->actingAs($usuario)
            ->get(route('admin.alm.articulos.index', ['search' => '7018-125']))
            ->assertInertia(fn ($page) => $page->has('articulos.data', 1)
                ->where('articulos.data.0.descripcion', 'Electrodo 7018'));
    });

    it('cuenta la bandeja de lo que Compras tecleo sin clasificar', function () {
        Producto::factory()->count(3)->sinClasificar()->create();
        Producto::factory()->create();

        $this->actingAs(usuarioDeArticulos(['ver']))
            ->get(route('admin.alm.articulos.index'))
            ->assertInertia(fn ($page) => $page->where('sinClasificar', 3));
    });

    it('el alta sugiere el codigo con el que va a quedar etiquetado', function () {
        Producto::factory()->create(['codigo' => 'ART-00011']);

        $this->actingAs(usuarioDeArticulos(['crear']))
            ->get(route('admin.alm.articulos.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('codigoSugerido', 'ART-00012'));
    });
});

describe('permisos', function () {
    it('cierra el catalogo a quien no tiene permiso', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.alm.articulos.index'))
            ->assertForbidden();
    });

    it('ver un articulo no alcanza para editarlo', function () {
        $articulo = Producto::factory()->create();
        $usuario = usuarioDeArticulos(['ver']);

        $this->actingAs($usuario)->get(route('admin.alm.articulos.show', $articulo))->assertOk();
        $this->actingAs($usuario)->get(route('admin.alm.articulos.edit', $articulo))->assertForbidden();
        $this->actingAs($usuario)
            ->put(route('admin.alm.articulos.update', $articulo), articuloValido())
            ->assertForbidden();
    });

    it('no expone una ruta para borrar articulos', function () {
        expect(fn () => route('admin.alm.articulos.destroy', 1))->toThrow(Exception::class);
    });
});
