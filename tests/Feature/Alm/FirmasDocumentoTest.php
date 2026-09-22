<?php

use App\Enums\Alm\DocumentoAlm;
use App\Models\Alm\Almacen;
use App\Models\Alm\FirmaDocumento;
use App\Models\Alm\Salida;
use App\Models\User;
use App\Services\Alm\FirmasDelFormato;
use Spatie\Permission\Models\Permission;

/**
 * La pantalla que decide qué rayas de firma lleva cada formato impreso.
 *
 * No es un flujo de aprobación: nada queda detenido esperando a nadie. Lo que
 * se guarda aquí sólo cambia lo que sale en la hoja.
 */
function usuarioDeFirmas(array $permisos = ['alm.aprobaciones.ver']): User
{
    $usuario = User::factory()->create();

    foreach ($permisos as $nombre) {
        Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        $usuario->givePermissionTo($nombre);
    }

    return $usuario;
}

describe('la pantalla', function () {
    test('sin permiso no se abre', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.alm.aprobaciones.index'))
            ->assertForbidden();
    });

    test('lista los ocho documentos con su plantilla', function () {
        Almacen::factory()->create();

        $this->actingAs(usuarioDeFirmas())
            ->get(route('admin.alm.aprobaciones.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/almacen/aprobaciones/index')
                ->has('documentos', 8)
                ->where('documentos.0.valor', 'pedido')
                ->where('documentos.0.porDefecto.0.rotulo', 'Solicitó')
                ->where('documentos.0.porDefecto.0.fuente', 'solicitante'));
    });

    test('quien solo puede ver no recibe el permiso de configurar', function () {
        Almacen::factory()->create();

        $this->actingAs(usuarioDeFirmas())
            ->get(route('admin.alm.aprobaciones.index'))
            ->assertInertia(fn ($page) => $page->where('puedeConfigurar', false));
    });
});

describe('guardar', function () {
    test('sin el permiso de configurar se rechaza', function () {
        $almacen = Almacen::factory()->create();

        $this->actingAs(usuarioDeFirmas())
            ->put(route('admin.alm.aprobaciones.update', $almacen), ['documentos' => []])
            ->assertForbidden();
    });

    test('reemplaza las firmas del almacen y las deja en orden', function () {
        $almacen = Almacen::factory()->create();
        $usuario = User::factory()->create();

        $this->actingAs(usuarioDeFirmas(['alm.aprobaciones.ver', 'alm.aprobaciones.configurar']))
            ->put(route('admin.alm.aprobaciones.update', $almacen), [
                'documentos' => [[
                    'documento' => 'salida',
                    'firmas' => [
                        ['rotulo' => 'Entregó', 'fuente' => 'entregador', 'usuarios' => []],
                        ['rotulo' => 'Visto bueno', 'fuente' => null, 'usuarios' => [$usuario->id]],
                    ],
                ]],
            ])
            ->assertRedirect();

        $firmas = FirmaDocumento::query()->where('almacen_id', $almacen->id)->orderBy('orden')->get();

        expect($firmas)->toHaveCount(2)
            ->and($firmas[0]->rotulo)->toBe('Entregó')
            ->and($firmas[0]->orden)->toBe(1)
            ->and($firmas[0]->fuente)->toBe('entregador')
            ->and($firmas[1]->orden)->toBe(2)
            ->and($firmas[1]->usuarios->pluck('id')->all())->toBe([$usuario->id]);
    });

    test('un documento sin renglones borra lo que hubiera', function () {
        $almacen = Almacen::factory()->create();
        FirmaDocumento::factory()->create(['almacen_id' => $almacen->id, 'documento' => DocumentoAlm::Salida]);

        $this->actingAs(usuarioDeFirmas(['alm.aprobaciones.ver', 'alm.aprobaciones.configurar']))
            ->put(route('admin.alm.aprobaciones.update', $almacen), [
                'documentos' => [['documento' => 'salida', 'firmas' => []]],
            ])
            ->assertRedirect();

        expect(FirmaDocumento::query()->where('almacen_id', $almacen->id)->count())->toBe(0);
    });

    test('no guarda una fuente que ese documento no sabe dar', function () {
        $almacen = Almacen::factory()->create();

        $this->actingAs(usuarioDeFirmas(['alm.aprobaciones.ver', 'alm.aprobaciones.configurar']))
            ->put(route('admin.alm.aprobaciones.update', $almacen), [
                'documentos' => [[
                    'documento' => 'ajuste',
                    // `enviador` es de la transferencia, no del ajuste.
                    'firmas' => [['rotulo' => 'Despachó', 'fuente' => 'enviador', 'usuarios' => []]],
                ]],
            ])
            ->assertSessionHasErrors('documentos.0.firmas.0.fuente');

        expect(FirmaDocumento::query()->count())->toBe(0);
    });

    test('un formato no lleva mas de seis firmas', function () {
        $almacen = Almacen::factory()->create();

        $this->actingAs(usuarioDeFirmas(['alm.aprobaciones.ver', 'alm.aprobaciones.configurar']))
            ->put(route('admin.alm.aprobaciones.update', $almacen), [
                'documentos' => [[
                    'documento' => 'salida',
                    'firmas' => array_fill(0, 7, ['rotulo' => 'Firma', 'fuente' => null, 'usuarios' => []]),
                ]],
            ])
            ->assertSessionHasErrors('documentos.0.firmas');
    });

    test('las firmas de un almacen no tocan las de otro', function () {
        $general = Almacen::factory()->create();
        $obra = Almacen::factory()->create();
        FirmaDocumento::factory()->create(['almacen_id' => $obra->id, 'rotulo' => 'Residente']);

        $this->actingAs(usuarioDeFirmas(['alm.aprobaciones.ver', 'alm.aprobaciones.configurar']))
            ->put(route('admin.alm.aprobaciones.update', $general), [
                'documentos' => [['documento' => 'salida', 'firmas' => [
                    ['rotulo' => 'Jefe de almacén', 'fuente' => null, 'usuarios' => []],
                ]]],
            ]);

        expect(FirmaDocumento::query()->where('almacen_id', $obra->id)->value('rotulo'))->toBe('Residente');
    });
});

describe('lo que sale en la hoja', function () {
    test('un almacen sin configurar imprime la plantilla de siempre', function () {
        $salida = Salida::factory()->create(['almacen_id' => Almacen::factory()]);

        $firmas = app(FirmasDelFormato::class)->para(DocumentoAlm::Salida, $salida->almacen_id, $salida);

        expect($firmas)->toHaveCount(2)
            ->and($firmas[0]['rol'])->toBe('Entregó - Almacén')
            ->and($firmas[1]['rol'])->toBe('Recibió de conformidad');
    });

    test('la raya sin usuarios ni fuente va en blanco', function () {
        $salida = Salida::factory()->create(['almacen_id' => Almacen::factory()]);
        FirmaDocumento::factory()->create([
            'almacen_id' => $salida->almacen_id,
            'documento' => DocumentoAlm::Salida,
            'orden' => 1,
            'rotulo' => 'Jefe de almacén',
        ]);

        $firmas = app(FirmasDelFormato::class)->para(DocumentoAlm::Salida, $salida->almacen_id, $salida);

        expect($firmas)->toBe([['nombre' => null, 'rol' => 'Jefe de almacén']]);
    });

    test('la fuente imprime el nombre que el documento ya sabe', function () {
        $entregador = User::factory()->create(['name' => 'M. Rangel']);
        $salida = Salida::factory()->create([
            'almacen_id' => Almacen::factory(),
            'entregado_por' => $entregador->id,
        ]);
        FirmaDocumento::factory()->deFuente('entregador')->create([
            'almacen_id' => $salida->almacen_id,
            'documento' => DocumentoAlm::Salida,
            'orden' => 1,
            'rotulo' => 'Entregó',
        ]);

        $firmas = app(FirmasDelFormato::class)->para(DocumentoAlm::Salida, $salida->almacen_id, $salida);

        expect($firmas[0]['nombre'])->toBe('M. Rangel');
    });

    test('los usuarios elegidos ganan sobre la fuente y se imprimen juntos', function () {
        $salida = Salida::factory()->create([
            'almacen_id' => Almacen::factory(),
            'entregado_por' => User::factory()->create(['name' => 'M. Rangel'])->id,
        ]);
        $firma = FirmaDocumento::factory()->deFuente('entregador')->create([
            'almacen_id' => $salida->almacen_id,
            'documento' => DocumentoAlm::Salida,
            'orden' => 1,
            'rotulo' => 'Entregó',
        ]);
        $firma->usuarios()->sync([
            User::factory()->create(['name' => 'J. Briones'])->id,
            User::factory()->create(['name' => 'L. Ortega'])->id,
        ]);

        $firmas = app(FirmasDelFormato::class)->para(DocumentoAlm::Salida, $salida->almacen_id, $salida);

        expect($firmas[0]['nombre'])->toContain('J. Briones')
            ->and($firmas[0]['nombre'])->toContain('L. Ortega')
            ->and($firmas[0]['nombre'])->toContain(' / ')
            ->and($firmas[0]['nombre'])->not->toContain('M. Rangel');
    });

    test('el formato impreso usa lo configurado', function () {
        $salida = Salida::factory()->create(['almacen_id' => Almacen::factory()]);
        FirmaDocumento::factory()->create([
            'almacen_id' => $salida->almacen_id,
            'documento' => DocumentoAlm::Salida,
            'orden' => 1,
            'rotulo' => 'Residente de obra',
        ]);

        $html = view('pdf.alm.formato-salida', [
            'salida' => $salida->load('detalles'),
            'firmas' => app(FirmasDelFormato::class)->para(DocumentoAlm::Salida, $salida->almacen_id, $salida),
        ])->render();

        expect($html)->toContain('Residente de obra')
            ->and($html)->not->toContain('Recibió de conformidad');
    });
});
