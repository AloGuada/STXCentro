<?php

use App\Enums\Alm\ActivoEstatus;
use App\Enums\Alm\PrestamoEstatus;
use App\Models\Alm\Activo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Alm\Prestamo;
use App\Models\Alm\PrestamoDetalle;
use App\Models\User;
use App\Services\Alm\RegistradorPiezas;
use App\Services\Alm\RegistradorPrestamos;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>  $permisos
 */
function usuarioDePrestamos(array $permisos = ['alm.prestamos.ver', 'alm.prestamos.crear', 'alm.devoluciones.ver', 'alm.devoluciones.crear']): User
{
    $user = User::factory()->create();

    $nombres = [...$permisos, 'alm.almacenes.ver-todos'];

    foreach ($nombres as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($nombres);

    return $user;
}

/**
 * Un almacén con una pulidora serializada y 10 extensiones por cantidad.
 *
 * @return array{almacen: Almacen, pulidora: Activo, extension: Articulo}
 */
function almacenConActivos(): array
{
    $almacen = Almacen::factory()->create();
    $registrador = app(RegistradorPiezas::class);

    $articuloPulidora = Articulo::factory()->porPieza()->create(['descripcion' => 'Pulidora']);
    [$pulidora] = $registrador->alta($articuloPulidora, $almacen, [['no_serie' => 'PUL-7', 'condicion' => 'Buena']]);

    $extension = Articulo::factory()->activoPorCantidad()->create(['descripcion' => 'Extensión 25 m']);
    $registrador->altaPorCantidad($extension, $almacen, 10, 100);

    return ['almacen' => $almacen, 'pulidora' => $pulidora, 'extension' => $extension];
}

/**
 * @param  list<array<string, mixed>>  $renglones
 * @return array<string, mixed>
 */
function prestamoValido(Almacen $almacen, User $responsable, array $renglones): array
{
    return [
        'almacen_id' => $almacen->id,
        'responsable_id' => $responsable->id,
        'fecha_salida' => today()->toDateString(),
        'fecha_retorno_esperada' => today()->addWeek()->toDateString(),
        'renglones' => $renglones,
    ];
}

describe('prestar', function () {
    it('marca la pieza como prestada y aparta la cantidad, sin tocar el kardex', function () {
        ['almacen' => $almacen, 'pulidora' => $pulidora, 'extension' => $extension] = almacenConActivos();
        $user = usuarioDePrestamos();
        $movimientosAntes = Movimiento::count();

        $this->actingAs($user)
            ->post(route('admin.alm.prestamos.store'), prestamoValido($almacen, $user, [
                ['articulo_id' => $pulidora->articulo_id, 'activo_id' => $pulidora->id, 'condicion_salida' => 'Buena'],
                ['articulo_id' => $extension->id, 'cantidad' => 4],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $prestamo = Prestamo::firstOrFail();
        $existencia = Existencia::query()->where('articulo_id', $extension->id)->firstOrFail();

        expect($prestamo->folio)->toStartWith('PRE-')
            ->and($prestamo->estatus)->toBe(PrestamoEstatus::Abierto)
            ->and($prestamo->detalles()->count())->toBe(2)
            ->and($pulidora->refresh()->estatus)->toBe(ActivoEstatus::Prestado)
            ->and((float) $existencia->cantidad)->toBe(10.0)
            ->and((float) $existencia->prestado)->toBe(4.0)
            ->and($existencia->disponibleParaPrestar())->toBe(6.0)
            ->and(Movimiento::count())->toBe($movimientosAntes);
    });

    it('no presta una pieza que ya está afuera ni más cantidad de la que hay libre', function () {
        ['almacen' => $almacen, 'pulidora' => $pulidora, 'extension' => $extension] = almacenConActivos();
        $user = usuarioDePrestamos();

        app(RegistradorPrestamos::class)->prestar($almacen, [
            'responsable_id' => $user->id,
            'fecha_salida' => today()->toDateString(),
        ], [
            ['articulo_id' => $pulidora->articulo_id, 'activo_id' => $pulidora->id],
            ['articulo_id' => $extension->id, 'cantidad' => 8],
        ]);

        $this->actingAs($user)
            ->post(route('admin.alm.prestamos.store'), prestamoValido($almacen, $user, [
                ['articulo_id' => $pulidora->articulo_id, 'activo_id' => $pulidora->id],
            ]))
            ->assertSessionHasErrors('renglones.0.activo_id');

        $this->actingAs($user)
            ->post(route('admin.alm.prestamos.store'), prestamoValido($almacen, $user, [
                ['articulo_id' => $extension->id, 'cantidad' => 3],
            ]))
            ->assertSessionHasErrors('renglones.0.cantidad');

        expect(Prestamo::count())->toBe(1);
    });

    it('un insumo no se presta, y una pieza no se presta por cantidad', function () {
        ['almacen' => $almacen, 'pulidora' => $pulidora] = almacenConActivos();
        $user = usuarioDePrestamos();
        $tornillo = Articulo::factory()->create();
        Existencia::factory()->conSaldo(100)->create(['almacen_id' => $almacen->id, 'articulo_id' => $tornillo->id, 'producto_id' => $tornillo->producto_id]);

        $this->actingAs($user)
            ->post(route('admin.alm.prestamos.store'), prestamoValido($almacen, $user, [
                ['articulo_id' => $tornillo->id, 'cantidad' => 5],
            ]))
            ->assertSessionHasErrors('renglones.0.articulo_id');

        $this->actingAs($user)
            ->post(route('admin.alm.prestamos.store'), prestamoValido($almacen, $user, [
                ['articulo_id' => $pulidora->articulo_id, 'cantidad' => 1],
            ]))
            ->assertSessionHasErrors('renglones.0.articulo_id');

        expect(Prestamo::count())->toBe(0);
    });

    it('prestables enseña las piezas disponibles y lo libre de los activos por cantidad', function () {
        ['almacen' => $almacen, 'extension' => $extension] = almacenConActivos();
        $user = usuarioDePrestamos();

        app(RegistradorPrestamos::class)->prestar($almacen, ['responsable_id' => $user->id, 'fecha_salida' => today()->toDateString()], [
            ['articulo_id' => $extension->id, 'cantidad' => 3],
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.alm.prestamos.prestables', $almacen))
            ->assertOk()
            ->assertJsonCount(1, 'piezas')
            ->assertJsonPath('piezas.0.no_serie', 'PUL-7')
            ->assertJsonCount(1, 'por_cantidad')
            ->assertJsonPath('por_cantidad.0.disponible', 7);
    });

    it('pide su permiso y respeta los almacenes visibles', function () {
        ['almacen' => $almacen, 'extension' => $extension] = almacenConActivos();
        $sinPermiso = usuarioDePrestamos(['alm.prestamos.ver']);

        $this->actingAs($sinPermiso)
            ->post(route('admin.alm.prestamos.store'), prestamoValido($almacen, $sinPermiso, [
                ['articulo_id' => $extension->id, 'cantidad' => 1],
            ]))
            ->assertForbidden();

        $ajeno = User::factory()->create();
        Permission::firstOrCreate(['name' => 'alm.prestamos.crear', 'guard_name' => 'web']);
        $ajeno->givePermissionTo('alm.prestamos.crear');

        $this->actingAs($ajeno)
            ->post(route('admin.alm.prestamos.store'), prestamoValido($almacen, $ajeno, [
                ['articulo_id' => $extension->id, 'cantidad' => 1],
            ]))
            ->assertForbidden();
    });
});

describe('devolver', function () {
    it('la pieza vuelve disponible, la cantidad vuelve por partes y el resguardo cierra al final', function () {
        ['almacen' => $almacen, 'pulidora' => $pulidora, 'extension' => $extension] = almacenConActivos();
        $user = usuarioDePrestamos();

        $prestamo = app(RegistradorPrestamos::class)->prestar($almacen, ['responsable_id' => $user->id, 'fecha_salida' => today()->toDateString()], [
            ['articulo_id' => $pulidora->articulo_id, 'activo_id' => $pulidora->id, 'condicion_salida' => 'Buena'],
            ['articulo_id' => $extension->id, 'cantidad' => 4],
        ]);
        $renglonPieza = $prestamo->detalles->firstWhere('activo_id', $pulidora->id);
        $renglonCantidad = $prestamo->detalles->firstWhere('activo_id', null);

        $this->actingAs($user)
            ->post(route('admin.alm.devoluciones.store'), [
                'fecha' => today()->toDateString(),
                'renglones' => [
                    ['detalle_id' => $renglonPieza->id, 'condicion_retorno' => 'Carbones gastados'],
                    ['detalle_id' => $renglonCantidad->id, 'cantidad' => 3],
                ],
            ])
            ->assertRedirect(route('admin.alm.prestamos.show', $prestamo))
            ->assertSessionHasNoErrors();

        $existencia = Existencia::query()->where('articulo_id', $extension->id)->firstOrFail();

        expect($pulidora->refresh()->estatus)->toBe(ActivoEstatus::Disponible)
            ->and($pulidora->condicion)->toBe('Carbones gastados')
            ->and($renglonPieza->refresh()->condicion_retorno)->toBe('Carbones gastados')
            ->and((float) $renglonCantidad->refresh()->cantidad_devuelta)->toBe(3.0)
            ->and($renglonCantidad->pendiente())->toBe(1.0)
            ->and((float) $existencia->prestado)->toBe(1.0)
            ->and($prestamo->refresh()->estatus)->toBe(PrestamoEstatus::Abierto);

        $this->actingAs($user)
            ->post(route('admin.alm.devoluciones.store'), [
                'fecha' => today()->toDateString(),
                'renglones' => [['detalle_id' => $renglonCantidad->id, 'cantidad' => 1]],
            ])
            ->assertSessionHasNoErrors();

        expect($prestamo->refresh()->estatus)->toBe(PrestamoEstatus::Cerrado)
            ->and($prestamo->cerrado_en)->not->toBeNull()
            ->and((float) $existencia->refresh()->prestado)->toBe(0.0)
            ->and((float) $existencia->cantidad)->toBe(10.0);
    });

    it('una pieza dañada vuelve a reparación, y no se devuelve más de lo pendiente', function () {
        ['almacen' => $almacen, 'pulidora' => $pulidora, 'extension' => $extension] = almacenConActivos();
        $user = usuarioDePrestamos();

        $prestamo = app(RegistradorPrestamos::class)->prestar($almacen, ['responsable_id' => $user->id, 'fecha_salida' => today()->toDateString()], [
            ['articulo_id' => $pulidora->articulo_id, 'activo_id' => $pulidora->id],
            ['articulo_id' => $extension->id, 'cantidad' => 2],
        ]);
        $renglonPieza = $prestamo->detalles->firstWhere('activo_id', $pulidora->id);
        $renglonCantidad = $prestamo->detalles->firstWhere('activo_id', null);

        $this->actingAs($user)
            ->post(route('admin.alm.devoluciones.store'), [
                'fecha' => today()->toDateString(),
                'renglones' => [['detalle_id' => $renglonCantidad->id, 'cantidad' => 3]],
            ])
            ->assertSessionHasErrors('renglones.0.cantidad');

        $this->actingAs($user)
            ->post(route('admin.alm.devoluciones.store'), [
                'fecha' => today()->toDateString(),
                'renglones' => [['detalle_id' => $renglonPieza->id, 'en_reparacion' => true, 'condicion_retorno' => 'Motor quemado']],
            ])
            ->assertSessionHasNoErrors();

        expect($pulidora->refresh()->estatus)->toBe(ActivoEstatus::EnReparacion)
            ->and($prestamo->refresh()->estatus)->toBe(PrestamoEstatus::Abierto);

        // Ya devuelta: no se devuelve dos veces.
        $this->actingAs($user)
            ->post(route('admin.alm.devoluciones.store'), [
                'fecha' => today()->toDateString(),
                'renglones' => [['detalle_id' => $renglonPieza->id]],
            ])
            ->assertSessionHasErrors('renglones.0.detalle_id');
    });

    it('devolver pide su propio permiso', function () {
        ['almacen' => $almacen, 'extension' => $extension] = almacenConActivos();
        $user = usuarioDePrestamos(['alm.prestamos.ver', 'alm.prestamos.crear']);

        $prestamo = app(RegistradorPrestamos::class)->prestar($almacen, ['responsable_id' => $user->id, 'fecha_salida' => today()->toDateString()], [
            ['articulo_id' => $extension->id, 'cantidad' => 2],
        ]);

        $this->actingAs($user)
            ->post(route('admin.alm.devoluciones.store'), [
                'fecha' => today()->toDateString(),
                'renglones' => [['detalle_id' => $prestamo->detalles->first()->id, 'cantidad' => 2]],
            ])
            ->assertForbidden();

        expect(PrestamoDetalle::firstOrFail()->pendiente())->toBe(2.0);
    });
});

describe('las pantallas', function () {
    it('el listado trae los resguardos con su avance y marca los vencidos', function () {
        ['almacen' => $almacen, 'extension' => $extension] = almacenConActivos();
        $user = usuarioDePrestamos();

        app(RegistradorPrestamos::class)->prestar($almacen, [
            'responsable_id' => $user->id,
            'fecha_salida' => today()->subDays(10)->toDateString(),
            'fecha_retorno_esperada' => today()->subDays(3)->toDateString(),
        ], [['articulo_id' => $extension->id, 'cantidad' => 2]]);

        $this->actingAs($user)
            ->get(route('admin.alm.prestamos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/almacen/prestamos/index')
                ->has('prestamos.data', 1)
                ->where('prestamos.data.0.vencido', true)
                ->where('prestamos.data.0.dias_fuera', 10)
                ->where('prestamos.data.0.pendiente', 2)
                ->where('resumen.vencidos', 1));
    });

    it('la ficha, el PDF y la pantalla de devolución abren', function () {
        ['almacen' => $almacen, 'pulidora' => $pulidora] = almacenConActivos();
        $user = usuarioDePrestamos();

        $prestamo = app(RegistradorPrestamos::class)->prestar($almacen, ['responsable_id' => $user->id, 'fecha_salida' => today()->toDateString()], [
            ['articulo_id' => $pulidora->articulo_id, 'activo_id' => $pulidora->id],
        ]);

        $this->actingAs($user)
            ->get(route('admin.alm.prestamos.show', $prestamo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/almacen/prestamos/show')
                ->where('prestamo.folio', $prestamo->folio)
                ->has('detalles', 1)
                ->where('detalles.0.no_serie', 'PUL-7')
                ->where('puede_devolver', true));

        $this->actingAs($user)
            ->get(route('admin.alm.prestamos.pdf', $prestamo))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('admin.alm.devoluciones.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/almacen/devoluciones/create')
                ->has('responsables', 1)
                ->has('responsables.0.renglones', 1));

        $this->actingAs($user)
            ->get(route('admin.alm.devoluciones.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/almacen/devoluciones/index')
                ->where('pendientes', 1));
    });
});
