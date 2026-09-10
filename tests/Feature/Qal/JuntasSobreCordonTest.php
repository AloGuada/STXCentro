<?php

use App\Enums\Qal\AmbitoPunto;
use App\Enums\Qal\EstatusModelo;
use App\Models\Concepto;
use App\Models\Prod\Pieza;
use App\Models\Qal\Junta;
use App\Models\Qal\Modelo;
use App\Models\Qal\ModeloCordon;
use App\Models\Qal\ModeloMarca;
use App\Models\Qal\PuntoInspeccion;
use App\Models\User;
use Database\Seeders\QalPuntosInspeccionSeeder;
use Spatie\Permission\Models\Permission;

/**
 * Juntas sobre el cordón del modelo 3D.
 *
 * En soldado, la pieza escaneada monta el visor de su marca y cada junta se
 * captura sobre un cordón en lugar de numerarse a mano. Se comprueba que la
 * junta apunta a su cordón, que un cordón de otra marca no se acepta y que el
 * modelo pinta cada cordón con la última junta de cada pieza.
 */
beforeEach(fn () => $this->seed(QalPuntosInspeccionSeeder::class));

function inspectorDelVisor(): User
{
    foreach (['qal.inspecciones.crear', 'qal.modelos.ver'] as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo(['qal.inspecciones.crear', 'qal.modelos.ver']);

    return $usuario;
}

/**
 * Una pieza de la marca SX-CM2-11 y el modelo 3D de su obra con el cordón S5.
 *
 * @return array{0: Pieza, 1: ModeloMarca, 2: ModeloCordon}
 */
function piezaConModelo(): array
{
    $concepto = Concepto::factory()->create(['marca' => 'SX-CM2-11']);
    $pieza = Pieza::factory()->create(['concepto_id' => $concepto->id]);
    $modelo = Modelo::factory()->create(['obra_id' => $concepto->obra_id]);
    $marca = ModeloMarca::factory()->create(['modelo_id' => $modelo->id, 'marca' => 'SX-CM2-11', 'archivo' => 'SX-CM2-11']);
    $cordon = ModeloCordon::factory()->create(['modelo_marca_id' => $marca->id, 'numero' => 5]);

    return [$pieza, $marca, $cordon];
}

/**
 * @return array<string, mixed>
 */
function soldadoSobreCordon(Pieza $pieza, ModeloCordon $cordon, string $poros = 'OK'): array
{
    $puntos = PuntoInspeccion::query()->where('ambito', AmbitoPunto::Junta->value)->pluck('clave')
        ->mapWithKeys(fn (string $clave): array => [$clave => 'OK'])->all();

    return [
        'obra_id' => $pieza->catalogo->obra_id,
        'fase' => '2ª',
        'subetapa' => 'soldado',
        'fecha' => now()->toDateString(),
        'prod_pieza_id' => $pieza->id,
        'kg' => 850,
        'estatus' => $poros === 'OK' ? 'liberado' : 'rechazado',
        'puntos' => ['p2_elem' => '6'],
        'juntas' => [[
            'identificador' => $cordon->identificador(),
            'tipo' => 'filete',
            'cordon_id' => $cordon->id,
            'puntos' => [...$puntos, 'm_poros' => $poros],
        ]],
    ];
}

test('la junta capturada sobre un cordon apunta a su fila', function () {
    [$pieza, , $cordon] = piezaConModelo();

    $this->actingAs(inspectorDelVisor())
        ->post(route('admin.qal.inspecciones.store'), soldadoSobreCordon($pieza, $cordon))
        ->assertSessionHasNoErrors();

    expect(Junta::sole()->only(['cordon_id', 'identificador']))->toBe(['cordon_id' => $cordon->id, 'identificador' => 'S5']);
});

test('un cordon de otra marca no se acepta aunque sea del mismo modelo', function () {
    [$pieza, $marca] = piezaConModelo();
    $ajeno = ModeloCordon::factory()->create([
        'modelo_marca_id' => ModeloMarca::factory()->create(['modelo_id' => $marca->modelo_id, 'marca' => 'SX-TP1-1'])->id,
    ]);

    $this->actingAs(inspectorDelVisor())
        ->post(route('admin.qal.inspecciones.store'), soldadoSobreCordon($pieza, $ajeno))
        ->assertSessionHasErrors('juntas.0.cordon_id');

    expect(Junta::count())->toBe(0);
});

test('al escanear la pieza se sabe que marca del modelo convertido le toca', function () {
    [$pieza, $marca] = piezaConModelo();
    // Una versión más nueva que todavía se está convirtiendo no cuenta.
    $enProceso = Modelo::factory()->pendiente()->create(['obra_id' => $marca->modelo->obra_id, 'version' => 2]);
    ModeloMarca::factory()->create(['modelo_id' => $enProceso->id, 'marca' => 'SX-CM2-11']);

    $this->actingAs(inspectorDelVisor())
        ->getJson(route('admin.qal.piezas.resolver', ['codigo' => $pieza->qr]))
        ->assertJsonPath('modelo_marca_id', $marca->id)
        ->assertJsonPath('modelo_3d', 'listo');

    expect($enProceso->estatus)->toBe(EstatusModelo::Pendiente);
});

test('sin visor, la pieza dice que le falta a la obra', function () {
    $concepto = Concepto::factory()->create(['marca' => 'SX-CM2-11']);
    $pieza = Pieza::factory()->create(['concepto_id' => $concepto->id]);
    $usuario = inspectorDelVisor();
    $estado = fn () => $this->actingAs($usuario)
        ->getJson(route('admin.qal.piezas.resolver', ['codigo' => $pieza->qr]))
        ->assertJsonPath('modelo_marca_id', null)
        ->json('modelo_3d');

    expect($estado())->toBe('sin_modelo');

    $modelo = Modelo::factory()->create(['obra_id' => $concepto->obra_id, 'estatus' => EstatusModelo::Error]);
    expect($estado())->toBe('error');

    $modelo->update(['estatus' => EstatusModelo::Listo]);
    ModeloMarca::factory()->create(['modelo_id' => $modelo->id, 'marca' => 'SX-TP1-1']);
    expect($estado())->toBe('sin_marca');

    Modelo::factory()->pendiente()->create(['obra_id' => $concepto->obra_id, 'version' => 2]);
    expect($estado())->toBe('convirtiendo');
});

test('el cordon se pinta con la ultima junta de cada pieza', function () {
    [$pieza, $marca, $cordon] = piezaConModelo();
    $usuario = inspectorDelVisor();

    $this->actingAs($usuario)->post(route('admin.qal.inspecciones.store'), soldadoSobreCordon($pieza, $cordon, 'Defecto'));

    $this->actingAs($usuario)
        ->getJson(route('admin.qal.modelos.marca', $marca))
        ->assertJsonPath('cordones.0.estado', 'defecto')
        ->assertJsonPath('cordones.0.con_defecto', 1);

    // La pieza se reparó y se reinspeccionó: su última junta ya está bien.
    $this->actingAs($usuario)->post(route('admin.qal.inspecciones.store'), soldadoSobreCordon($pieza, $cordon));

    $this->actingAs($usuario)
        ->getJson(route('admin.qal.modelos.marca', $marca))
        ->assertJsonPath('cordones.0.estado', 'correcta')
        ->assertJsonPath('cordones.0.correctas', 1)
        ->assertJsonPath('cordones.0.con_defecto', 0)
        ->assertJsonPath('glb_url', $marca->glbUrl());
});
