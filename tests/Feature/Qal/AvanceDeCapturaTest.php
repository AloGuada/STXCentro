<?php

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * La pestaña Registros de la captura: las marcas de la obra con su avance y,
 * al abrir una, cada QR con la última inspección de cada etapa. Es «Lotes por
 * marca» de la aplicación anterior con la pieza que ya viene de Producción.
 */
function usuarioQueCaptura(): User
{
    Permission::firstOrCreate(['name' => 'qal.inspecciones.crear', 'guard_name' => 'web']);

    $usuario = User::factory()->create();
    $usuario->givePermissionTo('qal.inspecciones.crear');

    return $usuario;
}

test('las marcas de la obra llegan con cuantas piezas van y como salieron', function () {
    $marca = Concepto::factory()->create(['marca' => 'SX-CM1-1']);
    $obraId = $marca->obra_id;
    [$limpia, $retrabajada, $enArmado, $sinVer] = Pieza::factory()->count(4)->create(['concepto_id' => $marca->id]);
    // Otra marca de la misma obra sin ninguna inspección.
    $otra = Concepto::factory()->create(['obra_id' => $obraId, 'catalogo_id' => $marca->catalogo_id, 'marca' => 'SX-TA1-2']);
    Pieza::factory()->count(2)->create(['concepto_id' => $otra->id]);

    Inspeccion::factory()->dePieza($limpia, FaseTransformacion::Segunda, Subetapa::Soldado)->create(['estatus' => EstatusInspeccion::Liberado]);
    Inspeccion::factory()->dePieza($retrabajada, FaseTransformacion::Segunda, Subetapa::Soldado)->create(['estatus' => EstatusInspeccion::Rechazado, 'fecha' => now()->subDay()]);
    Inspeccion::factory()->dePieza($retrabajada, FaseTransformacion::Segunda, Subetapa::Soldado)->create(['estatus' => EstatusInspeccion::Liberado, 'numero_inspeccion' => 2]);
    Inspeccion::factory()->dePieza($enArmado, FaseTransformacion::Segunda, Subetapa::ArmadoVestido)->create(['estatus' => EstatusInspeccion::Pendiente]);

    $this->actingAs(usuarioQueCaptura())
        ->get(route('admin.qal.formularios', ['obra' => $obraId]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('avance', 2)
            ->where('avance.0.marca', 'SX-CM1-1')
            ->where('avance.0.piezas', 4)
            ->where('avance.0.inspeccionadas', 3)
            // La retrabajada acabó liberada: cuenta como liberada, no como rechazada.
            ->where('avance.0.liberadas', 2)
            ->where('avance.0.rechazadas', 0)
            ->where('avance.0.pendientes', 1)
            ->where('avance.1.marca', 'SX-TA1-2')
            ->where('avance.1.piezas', 2)
            ->where('avance.1.inspeccionadas', 0)
            ->where('piezasDeMarca', null));
});

test('al abrir una marca cada qr trae la ultima inspeccion de cada etapa', function () {
    $marca = Concepto::factory()->create();
    $pieza = Pieza::factory()->create(['concepto_id' => $marca->id, 'qr' => '155745', 'qs' => '1']);
    Pieza::factory()->create(['concepto_id' => $marca->id, 'qr' => '155746', 'qs' => '2']);

    $primera = Inspeccion::factory()->dePieza($pieza, FaseTransformacion::Segunda, Subetapa::Soldado)->create(['estatus' => EstatusInspeccion::Rechazado, 'fecha' => now()->subDay()]);
    $segunda = Inspeccion::factory()->dePieza($pieza, FaseTransformacion::Segunda, Subetapa::Soldado)->create(['estatus' => EstatusInspeccion::Liberado, 'numero_inspeccion' => 2]);
    $pintura = Inspeccion::factory()->dePieza($pieza, FaseTransformacion::Tercera)->create(['estatus' => EstatusInspeccion::Rechazado]);

    $this->actingAs(usuarioQueCaptura())
        ->get(route('admin.qal.formularios', ['obra' => $marca->obra_id, 'marca' => $marca->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('piezasDeMarca.conceptoId', $marca->id)
            ->has('piezasDeMarca.piezas', 2)
            ->where('piezasDeMarca.piezas.0.qr', '155745')
            ->where('piezasDeMarca.piezas.0.etapas.armado', null)
            ->where('piezasDeMarca.piezas.0.etapas.soldado.id', $segunda->id)
            ->where('piezasDeMarca.piezas.0.etapas.soldado.estatus', 'liberado')
            ->where('piezasDeMarca.piezas.0.etapas.soldado.inspecciones', 2)
            ->where('piezasDeMarca.piezas.0.etapas.pintura.id', $pintura->id)
            ->where('piezasDeMarca.piezas.0.etapas.pintura.estatus', 'rechazado')
            ->where('piezasDeMarca.piezas.1.etapas.soldado', null));

    expect($primera->id)->not->toBe($segunda->id);
});

test('el catalogo congelado no aparece en el avance', function () {
    $marca = Concepto::factory()->create();
    Pieza::factory()->create(['concepto_id' => $marca->id]);
    Catalogo::query()->whereKey($marca->catalogo_id)->update(['vigente' => false]);

    $this->actingAs(usuarioQueCaptura())
        ->get(route('admin.qal.formularios', ['obra' => $marca->obra_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('avance', []));
});
