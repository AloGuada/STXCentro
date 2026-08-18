<?php

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Ubicacion;
use App\Models\Costos\Producto;
use App\Models\User;
use App\Services\Alm\AlmacenLedger;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>  $permisos
 */
function usuarioDeUbicaciones(array $permisos = ['ver', 'crear', 'editar']): User
{
    $user = User::factory()->create();

    $nombres = array_map(fn (string $accion): string => "alm.ubicaciones.{$accion}", $permisos);
    $nombres[] = 'alm.almacenes.ver-todos';

    foreach ($nombres as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($nombres);

    return $user;
}

describe('el arbol', function () {
    it('devuelve los lugares en el orden en que se recorren, con su profundidad', function () {
        $almacen = Almacen::factory()->create();
        $pasillo = Ubicacion::factory()->de($almacen)->create(['codigo' => 'A', 'nombre' => 'Pasillo A']);
        $rack = Ubicacion::factory()->bajo($pasillo)->create(['codigo' => 'A-1', 'nombre' => 'Rack A-1']);
        Ubicacion::factory()->bajo($rack)->create(['codigo' => 'A-1-1', 'nombre' => 'Nivel 1']);
        Ubicacion::factory()->de($almacen)->create(['codigo' => 'B', 'nombre' => 'Pasillo B']);

        $this->actingAs(usuarioDeUbicaciones(['ver']))
            ->get(route('admin.alm.ubicaciones.index', ['almacen_id' => $almacen->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('ubicaciones', 4)
                ->where('ubicaciones.0.nombre', 'Pasillo A')
                ->where('ubicaciones.0.nivel', 0)
                ->where('ubicaciones.1.nombre', 'Rack A-1')
                ->where('ubicaciones.1.nivel', 1)
                ->where('ubicaciones.2.nombre', 'Nivel 1')
                ->where('ubicaciones.2.nivel', 2)
                ->where('ubicaciones.3.nombre', 'Pasillo B')
                ->where('ubicaciones.3.nivel', 0));
    });

    it('arma la ruta legible subiendo por los padres', function () {
        $almacen = Almacen::factory()->create();
        $pasillo = Ubicacion::factory()->de($almacen)->create(['nombre' => 'Pasillo A']);
        $rack = Ubicacion::factory()->bajo($pasillo)->create(['nombre' => 'Rack A-1']);
        $nivel = Ubicacion::factory()->bajo($rack)->create(['nombre' => 'Nivel 2']);

        expect($nivel->ruta())->toBe('Pasillo A / Rack A-1 / Nivel 2');
    });

    it('cuenta cuantos articulos vive en cada lugar', function () {
        $almacen = Almacen::factory()->create();
        $rack = Ubicacion::factory()->de($almacen)->create();
        $ledger = app(AlmacenLedger::class);

        foreach (Producto::factory()->count(2)->create() as $producto) {
            $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 10, 5);
            $ledger->bloquear($almacen->id, $producto->id)->update(['ubicacion_id' => $rack->id]);
        }

        $this->actingAs(usuarioDeUbicaciones(['ver']))
            ->get(route('admin.alm.ubicaciones.index', ['almacen_id' => $almacen->id]))
            ->assertInertia(fn ($page) => $page->where('ubicaciones.0.articulos', 2));
    });

    it('avisa cuanto material esta sin acomodar', function () {
        $almacen = Almacen::factory()->create();
        $ledger = app(AlmacenLedger::class);

        foreach (Producto::factory()->count(3)->create() as $producto) {
            $ledger->registrarPorProducto($almacen->id, $producto->id, MovimientoTipo::Entrada, 10, 5);
        }

        $this->actingAs(usuarioDeUbicaciones(['ver']))
            ->get(route('admin.alm.ubicaciones.index', ['almacen_id' => $almacen->id]))
            ->assertInertia(fn ($page) => $page->where('sinAcomodar', 3));
    });
});

describe('alta y edicion', function () {
    it('la clave es unica dentro del almacen pero se repite entre almacenes', function () {
        $ag = Almacen::factory()->create();
        $fak = Almacen::factory()->create();
        $usuario = usuarioDeUbicaciones();

        $fila = ['codigo' => 'C1', 'nombre' => 'Contenedor 1', 'tipo' => 'contenedor'];

        $this->actingAs($usuario)
            ->post(route('admin.alm.ubicaciones.store'), [...$fila, 'almacen_id' => $ag->id])
            ->assertSessionHasNoErrors();

        // Otro almacén sí puede tener su propio «Contenedor 1».
        $this->actingAs($usuario)
            ->post(route('admin.alm.ubicaciones.store'), [...$fila, 'almacen_id' => $fak->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($usuario)
            ->post(route('admin.alm.ubicaciones.store'), [...$fila, 'almacen_id' => $ag->id])
            ->assertSessionHasErrors('codigo');

        expect(Ubicacion::count())->toBe(2);
    });

    it('no deja colgar un lugar de otro almacen', function () {
        $ajeno = Ubicacion::factory()->create();

        $this->actingAs(usuarioDeUbicaciones())
            ->post(route('admin.alm.ubicaciones.store'), [
                'almacen_id' => Almacen::factory()->create()->id,
                'padre_id' => $ajeno->id,
                'codigo' => 'X-1',
                'nombre' => 'Nivel suelto',
                'tipo' => 'nivel',
            ])
            ->assertSessionHasErrors('padre_id');
    });

    it('no deja armar un ciclo al reasignar el padre', function () {
        $almacen = Almacen::factory()->create();
        $pasillo = Ubicacion::factory()->de($almacen)->create();
        $rack = Ubicacion::factory()->bajo($pasillo)->create();

        // Colgar el pasillo de su propio rack dejaría al árbol sin raíz y la
        // recursión que lo dibuja no terminaría.
        $this->actingAs(usuarioDeUbicaciones())
            ->put(route('admin.alm.ubicaciones.update', $pasillo), [
                'padre_id' => $rack->id,
                'codigo' => $pasillo->codigo,
                'nombre' => $pasillo->nombre,
                'tipo' => $pasillo->tipo->value,
            ])
            ->assertSessionHasErrors('padre_id');
    });

    it('no deja colgar un lugar de si mismo', function () {
        $ubicacion = Ubicacion::factory()->create();

        $this->actingAs(usuarioDeUbicaciones())
            ->put(route('admin.alm.ubicaciones.update', $ubicacion), [
                'padre_id' => $ubicacion->id,
                'codigo' => $ubicacion->codigo,
                'nombre' => $ubicacion->nombre,
                'tipo' => $ubicacion->tipo->value,
            ])
            ->assertSessionHasErrors('padre_id');
    });
});

describe('baja logica', function () {
    it('desactiva en vez de borrar, y arrastra lo que cuelga', function () {
        $almacen = Almacen::factory()->create();
        $pasillo = Ubicacion::factory()->de($almacen)->create();
        $rack = Ubicacion::factory()->bajo($pasillo)->create();
        $nivel = Ubicacion::factory()->bajo($rack)->create();

        $this->actingAs(usuarioDeUbicaciones())
            ->patch(route('admin.alm.ubicaciones.toggle', $pasillo))
            ->assertRedirect();

        expect(Ubicacion::count())->toBe(3)
            ->and($pasillo->refresh()->activa)->toBeFalse()
            // Un nivel vivo dentro de un rack retirado promete una dirección
            // que ya nadie puede recorrer.
            ->and($rack->refresh()->activa)->toBeFalse()
            ->and($nivel->refresh()->activa)->toBeFalse();
    });

    it('reactivar no arrastra a los hijos', function () {
        $almacen = Almacen::factory()->create();
        $pasillo = Ubicacion::factory()->de($almacen)->inactiva()->create();
        $rack = Ubicacion::factory()->bajo($pasillo)->inactiva()->create();

        $this->actingAs(usuarioDeUbicaciones())->patch(route('admin.alm.ubicaciones.toggle', $pasillo));

        expect($pasillo->refresh()->activa)->toBeTrue()
            ->and($rack->refresh()->activa)->toBeFalse();
    });

    it('no expone una ruta para borrar ubicaciones', function () {
        expect(fn () => route('admin.alm.ubicaciones.destroy', 1))->toThrow(Exception::class);
    });
});

describe('acomodar material', function () {
    it('asigna y quita el lugar de una existencia sin tocar el saldo', function () {
        $almacen = Almacen::factory()->create();
        $rack = Ubicacion::factory()->de($almacen)->create();
        $producto = Producto::factory()->create();

        app(AlmacenLedger::class)->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Entrada, 100, 10
        );

        $existencia = app(AlmacenLedger::class)->bloquear($almacen->id, $producto->id);
        $usuario = usuarioDeUbicaciones();

        $this->actingAs($usuario)
            ->patch(route('admin.alm.existencias.ubicacion', $existencia), ['ubicacion_id' => $rack->id])
            ->assertRedirect();

        expect($existencia->refresh()->ubicacion_id)->toBe($rack->id)
            // Acomodar no es un hecho contable: ni el saldo ni el kardex se mueven.
            ->and((float) $existencia->cantidad)->toBe(100.0)
            ->and($existencia->movimientos()->count())->toBe(1);

        $this->actingAs($usuario)
            ->patch(route('admin.alm.existencias.ubicacion', $existencia), ['ubicacion_id' => null]);

        expect($existencia->refresh()->ubicacion_id)->toBeNull();
    });

    it('no acepta un lugar de otro almacen', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        $ajeno = Ubicacion::factory()->create();

        app(AlmacenLedger::class)->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Entrada, 10, 5
        );
        $existencia = app(AlmacenLedger::class)->bloquear($almacen->id, $producto->id);

        $this->actingAs(usuarioDeUbicaciones())
            ->patch(route('admin.alm.existencias.ubicacion', $existencia), ['ubicacion_id' => $ajeno->id])
            ->assertSessionHasErrors('ubicacion_id');
    });

    it('tampoco acepta un lugar dado de baja', function () {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        $retirado = Ubicacion::factory()->de($almacen)->inactiva()->create();

        app(AlmacenLedger::class)->registrarPorProducto(
            $almacen->id, $producto->id, MovimientoTipo::Entrada, 10, 5
        );
        $existencia = app(AlmacenLedger::class)->bloquear($almacen->id, $producto->id);

        $this->actingAs(usuarioDeUbicaciones())
            ->patch(route('admin.alm.existencias.ubicacion', $existencia), ['ubicacion_id' => $retirado->id])
            ->assertSessionHasErrors('ubicacion_id');
    });
});

describe('permisos y visibilidad', function () {
    it('cierra la pantalla a quien no tiene permiso', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.alm.ubicaciones.index'))
            ->assertForbidden();
    });

    it('ver no alcanza para dar de alta ni para editar', function () {
        $ubicacion = Ubicacion::factory()->create();
        $usuario = usuarioDeUbicaciones(['ver']);

        $this->actingAs($usuario)->get(route('admin.alm.ubicaciones.index'))->assertOk();
        $this->actingAs($usuario)->post(route('admin.alm.ubicaciones.store'), [
            'almacen_id' => $ubicacion->almacen_id,
            'codigo' => 'Z-9',
            'nombre' => 'Zona nueva',
            'tipo' => 'zona',
        ])->assertForbidden();
        $this->actingAs($usuario)->patch(route('admin.alm.ubicaciones.toggle', $ubicacion))->assertForbidden();
    });

    it('solo ofrece los almacenes que el usuario puede ver', function () {
        $suyo = Almacen::factory()->create();
        Almacen::factory()->create();

        $usuario = User::factory()->create();
        Permission::firstOrCreate(['name' => 'alm.ubicaciones.ver', 'guard_name' => 'web']);
        $usuario->givePermissionTo('alm.ubicaciones.ver');
        $suyo->usuarios()->attach($usuario->getKey());

        $this->actingAs($usuario)
            ->get(route('admin.alm.ubicaciones.index'))
            ->assertInertia(fn ($page) => $page
                ->has('almacenes', 1)
                ->where('almacenes.0.id', $suyo->id));
    });
});
