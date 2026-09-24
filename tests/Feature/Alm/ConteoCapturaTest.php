<?php

use App\Enums\Alm\AjusteMotivo;
use App\Enums\Alm\ConteoEstatus;
use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Ajuste;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Conteo;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\User;
use App\Services\Alm\GeneradorProgramaConteo;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>  $permisos
 */
function quienCuenta(array $permisos = ['ver', 'capturar', 'cerrar']): User
{
    $user = User::factory()->create();

    $nombres = array_map(fn (string $accion): string => "alm.conteos.{$accion}", $permisos);
    $nombres[] = 'alm.almacenes.ver-todos';

    foreach ($nombres as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($nombres);

    return $user;
}

/**
 * Una hoja de un solo día con N artículos, cada uno con el saldo que se pida.
 *
 * @param  list<float>  $saldos
 */
function hojaConSaldos(array $saldos): Conteo
{
    $almacen = Almacen::factory()->create();

    foreach ($saldos as $saldo) {
        $articulo = Articulo::factory()->create();
        Existencia::factory()->conSaldo($saldo, 10)->create([
            'almacen_id' => $almacen->id,
            'articulo_id' => $articulo->id,
            'producto_id' => $articulo->producto_id,
        ]);
    }

    $programa = app(GeneradorProgramaConteo::class)->generar($almacen, CarbonImmutable::today(), [1, 2, 3, 4, 5, 6, 7], 50);

    return $programa->conteos->first()->load('detalles');
}

/**
 * @param  array<int, float|null>  $contadoPorOrden  orden => cantidad
 * @return array<string, mixed>
 */
function capturaDe(Conteo $hoja, array $contadoPorOrden): array
{
    return [
        'renglones' => $hoja->detalles
            ->filter(fn ($d) => array_key_exists($d->orden, $contadoPorOrden))
            ->map(fn ($d) => ['id' => $d->id, 'cantidad_contada' => $contadoPorOrden[$d->orden]])
            ->values()
            ->all(),
    ];
}

/**
 * Lo mismo, pero eligiendo el renglón por el saldo que tiene: el reparto de la
 * hoja es al azar, así que el orden no dice qué artículo es.
 *
 * @param  array<int|string, float>  $contadoPorSaldo  saldo del sistema => cantidad contada
 * @return array<string, mixed>
 */
function capturaPorSaldo(Conteo $hoja, array $contadoPorSaldo): array
{
    $hoja->load('detalles.existencia');

    return [
        'renglones' => $hoja->detalles
            ->map(fn ($d) => ['id' => $d->id, 'cantidad_contada' => $contadoPorSaldo[(string) (int) $d->existencia->cantidad]])
            ->values()
            ->all(),
    ];
}

describe('la captura', function () {
    it('guarda lo contado y sella el saldo del sistema en ese momento', function () {
        $hoja = hojaConSaldos([100, 50]);

        $this->actingAs(quienCuenta())
            ->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 97]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $hoja->refresh();
        $primero = $hoja->detalles->firstWhere('orden', 1);
        $segundo = $hoja->detalles->firstWhere('orden', 2);

        expect($hoja->estatus)->toBe(ConteoEstatus::Contando)
            ->and($hoja->responsable_id)->not->toBeNull()
            ->and((float) $primero->cantidad_contada)->toBe(97.0)
            ->and((float) $primero->cantidad_sistema)->toBeIn([100.0, 50.0])
            ->and($segundo->cantidad_contada)->toBeNull()
            ->and($segundo->cantidad_sistema)->toBeNull();
    });

    it('se puede guardar a medias y completar después', function () {
        $hoja = hojaConSaldos([10, 20]);
        $user = quienCuenta();

        $this->actingAs($user)->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 10]));
        $this->actingAs($user)->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [2 => 19]));

        expect($hoja->refresh()->detalles->whereNull('cantidad_contada'))->toHaveCount(0);
    });

    it('borrar la cantidad des-captura el renglón y regresa la hoja a pendiente si no queda nada', function () {
        $hoja = hojaConSaldos([10]);
        $user = quienCuenta();

        $this->actingAs($user)->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 10]));
        expect($hoja->refresh()->estatus)->toBe(ConteoEstatus::Contando);

        $this->actingAs($user)->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => null]))
            ->assertSessionHasNoErrors();

        $hoja->refresh();
        expect($hoja->estatus)->toBe(ConteoEstatus::Pendiente)
            ->and($hoja->detalles->first()->cantidad_contada)->toBeNull()
            ->and($hoja->detalles->first()->cantidad_sistema)->toBeNull();
    });

    it('no acepta contar en negativo ni renglones de otra hoja', function () {
        $hoja = hojaConSaldos([10]);
        $otra = hojaConSaldos([10]);
        $user = quienCuenta();

        $this->actingAs($user)
            ->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => -1]))
            ->assertSessionHasErrors('renglones.0.cantidad_contada');

        $this->actingAs($user)
            ->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($otra, [1 => 5]))
            ->assertSessionHasErrors('renglones');
    });

    it('sólo captura quien tiene el permiso', function () {
        $hoja = hojaConSaldos([10]);

        $this->actingAs(quienCuenta(['ver']))
            ->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 10]))
            ->assertForbidden();
    });

    it('no se captura sobre una hoja cerrada', function () {
        $hoja = hojaConSaldos([10]);
        $user = quienCuenta();

        $this->actingAs($user)->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 10]));
        $this->actingAs($user)->post(route('admin.alm.conteos.cerrar', $hoja));

        $this->actingAs($user)
            ->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 8]))
            ->assertSessionHasErrors('renglones');

        expect((float) $hoja->refresh()->detalles->first()->cantidad_contada)->toBe(10.0);
    });
});

describe('el cierre', function () {
    it('se niega mientras falte un renglón por contar', function () {
        $hoja = hojaConSaldos([10, 20]);
        $user = quienCuenta();

        $this->actingAs($user)->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 10]));

        $this->actingAs($user)
            ->post(route('admin.alm.conteos.cerrar', $hoja))
            ->assertSessionHasErrors('cierre');

        expect($hoja->refresh()->estatus)->toBe(ConteoEstatus::Contando)
            ->and(Ajuste::count())->toBe(0);
    });

    it('genera el ajuste de conteo físico y el kardex mueve sólo lo que descuadra', function () {
        $hoja = hojaConSaldos([100, 50, 8]);
        $user = quienCuenta();

        $this->actingAs($user)->patch(
            route('admin.alm.conteos.capturar', $hoja),
            capturaPorSaldo($hoja, [100 => 97, 50 => 50, 8 => 9]),
        );

        $this->actingAs($user)
            ->post(route('admin.alm.conteos.cerrar', $hoja), ['observaciones' => 'Faltaban 3 en el rack'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $hoja->refresh();
        $ajuste = Ajuste::firstOrFail();

        expect($hoja->estatus)->toBe(ConteoEstatus::Cerrado)
            ->and($hoja->fecha_cierre)->not->toBeNull()
            ->and($hoja->ajuste_id)->toBe($ajuste->id)
            ->and($hoja->observaciones)->toBe('Faltaban 3 en el rack')
            ->and($ajuste->motivo)->toBe(AjusteMotivo::ConteoFisico)
            ->and($ajuste->almacen_id)->toBe($hoja->almacen_id)
            ->and($ajuste->detalles()->count())->toBe(3)
            ->and($ajuste->detalles()->get()->filter(fn ($d) => abs((float) $d->diferencia) > 0)->count())->toBe(2)
            ->and(Movimiento::where('tipo', MovimientoTipo::Ajuste)->count())->toBe(2);

        $saldos = Existencia::query()->where('almacen_id', $hoja->almacen_id)->pluck('cantidad')->map(fn ($c) => (float) $c)->sort()->values()->all();

        expect($saldos)->toBe([9.0, 50.0, 97.0]);
    });

    it('cerrar sin diferencias deja el acta y no toca el kardex', function () {
        $hoja = hojaConSaldos([10]);
        $user = quienCuenta();

        $this->actingAs($user)->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 10]));
        $this->actingAs($user)->post(route('admin.alm.conteos.cerrar', $hoja))->assertSessionHasNoErrors();

        expect($hoja->refresh()->estatus)->toBe(ConteoEstatus::Cerrado)
            ->and(Ajuste::count())->toBe(1)
            ->and(Movimiento::count())->toBe(0);
    });

    it('el ajuste se mide contra el saldo de ahora, no contra el sellado al capturar', function () {
        $hoja = hojaConSaldos([100]);
        $user = quienCuenta();

        $this->actingAs($user)->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 100]));

        Existencia::query()->where('almacen_id', $hoja->almacen_id)->update(['cantidad' => 103]);

        $this->actingAs($user)->post(route('admin.alm.conteos.cerrar', $hoja));

        expect((float) Ajuste::firstOrFail()->detalles()->firstOrFail()->cantidad_sistema)->toBe(103.0)
            ->and((float) Existencia::query()->where('almacen_id', $hoja->almacen_id)->firstOrFail()->cantidad)->toBe(100.0);
    });

    it('guarda la hoja firmada si la suben al cerrar, y la enseña en la pantalla', function () {
        Storage::fake('public');
        $hoja = hojaConSaldos([10]);
        $user = quienCuenta();

        $this->actingAs($user)->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 10]));

        $this->actingAs($user)
            ->post(route('admin.alm.conteos.cerrar', $hoja), [
                'firmado' => UploadedFile::fake()->create('hoja-firmada.pdf', 120, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $hoja->refresh();

        expect($hoja->firmado_path)->toStartWith('alm/conteos-firmados/')
            ->and(Storage::disk('public')->exists($hoja->firmado_path))->toBeTrue();

        $this->actingAs($user)
            ->get(route('admin.alm.conteos.show', $hoja))
            ->assertInertia(fn ($page) => $page->where('conteo.firmado_url', Storage::disk('public')->url($hoja->firmado_path)));
    });

    it('la hoja firmada es opcional y sólo acepta PDF o foto', function () {
        Storage::fake('public');
        $hoja = hojaConSaldos([10]);
        $user = quienCuenta();

        $this->actingAs($user)->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 10]));

        $this->actingAs($user)
            ->post(route('admin.alm.conteos.cerrar', $hoja), [
                'firmado' => UploadedFile::fake()->create('hoja.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ])
            ->assertSessionHasErrors(['firmado']);

        expect($hoja->refresh()->estatus)->toBe(ConteoEstatus::Contando)
            ->and(Storage::disk('public')->allFiles())->toBe([]);

        $this->actingAs($user)->post(route('admin.alm.conteos.cerrar', $hoja))->assertSessionHasNoErrors();

        expect($hoja->refresh()->firmado_path)->toBeNull();
    });

    it('si el cierre se niega, el archivo subido no se queda en disco', function () {
        Storage::fake('public');
        $hoja = hojaConSaldos([10, 20]);
        $user = quienCuenta();

        // Sólo un renglón contado: el cierre se niega.
        $this->actingAs($user)->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 10]));

        $this->actingAs($user)
            ->post(route('admin.alm.conteos.cerrar', $hoja), [
                'firmado' => UploadedFile::fake()->create('hoja-firmada.pdf', 50, 'application/pdf'),
            ])
            ->assertSessionHasErrors(['cierre']);

        expect(Storage::disk('public')->allFiles())->toBe([])
            ->and($hoja->refresh()->firmado_path)->toBeNull();
    });

    it('con la hoja cerrada da el reporte con lo contado contra el sistema', function () {
        $hoja = hojaConSaldos([100, 50]);
        $user = quienCuenta();

        $this->actingAs($user)->patch(route('admin.alm.conteos.capturar', $hoja), capturaPorSaldo($hoja, [100 => 97, 50 => 50]));
        $this->actingAs($user)->post(route('admin.alm.conteos.cerrar', $hoja))->assertSessionHasNoErrors();

        $this->actingAs(quienCuenta(['ver']))
            ->get(route('admin.alm.conteos.reporte', $hoja))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('no hay reporte mientras la hoja siga abierta', function () {
        $hoja = hojaConSaldos([10]);

        $this->actingAs(quienCuenta())
            ->get(route('admin.alm.conteos.reporte', $hoja))
            ->assertNotFound();
    });

    it('cerrar pide su propio permiso', function () {
        $hoja = hojaConSaldos([10]);

        $this->actingAs(quienCuenta(['ver', 'capturar']))
            ->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 10]));

        $this->actingAs(quienCuenta(['ver', 'capturar']))
            ->post(route('admin.alm.conteos.cerrar', $hoja))
            ->assertForbidden();
    });
});

describe('el saldo del sistema en pantalla', function () {
    it('se esconde mientras la hoja está abierta o incompleta', function () {
        $hoja = hojaConSaldos([10, 20]);
        $user = quienCuenta();

        $this->actingAs($user)->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 10]));

        $this->actingAs($user)
            ->get(route('admin.alm.conteos.show', $hoja))
            ->assertInertia(fn ($page) => $page
                ->where('conteo.saldo_visible', false)
                ->where('conteo.completa', false)
                ->where('conteo.puede_capturar', true)
                ->where('conteo.puede_cerrar', true)
                ->where('conteo.renglones.0.cantidad_sistema', null));
    });

    it('se enseña a quien puede cerrar cuando la hoja está completa', function () {
        $hoja = hojaConSaldos([10]);
        $user = quienCuenta();

        $this->actingAs($user)->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 7]));

        $this->actingAs($user)
            ->get(route('admin.alm.conteos.show', $hoja))
            ->assertInertia(fn ($page) => $page
                ->where('conteo.saldo_visible', true)
                ->where('conteo.completa', true)
                ->where('conteo.renglones.0.cantidad_sistema', 10));

        $this->actingAs(quienCuenta(['ver', 'capturar']))
            ->get(route('admin.alm.conteos.show', $hoja))
            ->assertInertia(fn ($page) => $page
                ->where('conteo.saldo_visible', false)
                ->where('conteo.renglones.0.cantidad_sistema', null));
    });

    it('se enseña a todos con la hoja cerrada', function () {
        $hoja = hojaConSaldos([10]);
        $user = quienCuenta();

        $this->actingAs($user)->patch(route('admin.alm.conteos.capturar', $hoja), capturaDe($hoja, [1 => 7]));
        $this->actingAs($user)->post(route('admin.alm.conteos.cerrar', $hoja));

        $this->actingAs(quienCuenta(['ver']))
            ->get(route('admin.alm.conteos.show', $hoja))
            ->assertInertia(fn ($page) => $page
                ->where('conteo.saldo_visible', true)
                ->where('conteo.puede_capturar', false)
                ->where('conteo.puede_cerrar', false)
                ->where('conteo.ajuste_folio', Ajuste::firstOrFail()->folio)
                ->where('conteo.renglones.0.cantidad_sistema', 10));
    });
});
