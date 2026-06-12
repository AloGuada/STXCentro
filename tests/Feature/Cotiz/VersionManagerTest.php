<?php

use App\Models\Cotiz\Generadora;
use App\Models\Cotiz\GeneradoraRegistro;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\Merma;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraFleteViatico;
use App\Models\Cotiz\ObraVersion;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaRegistro;
use App\Models\Cotiz\Unidad;
use App\Services\Cotiz\TarjetaCalculator;
use App\Services\Cotiz\VersionManager;

beforeEach(function () {
    $this->vm = app(VersionManager::class);
    $this->calc = app(TarjetaCalculator::class);

    // Obra con una generadora→registro importado a una tarjeta + un item de fletes/viáticos.
    $kg = Unidad::factory()->create(['descripcion' => 'kg']);
    $this->insumo = Insumo::factory()->create(['unidad_id' => $kg->id, 'precio_unitario' => 100, 'peso_lineal' => 10]);
    $merma = Merma::factory()->create(['formula' => '']);

    $this->obra = Obra::factory()->create(['factor_contratista' => 1.15]);
    $this->gen = Generadora::factory()->create(['obra_id' => $this->obra->id]);
    $this->reg = GeneradoraRegistro::factory()->create([
        'generadora_id' => $this->gen->id,
        'material_origen_id' => $this->insumo->id,
        'ancho' => 2, 'largo' => 3, 'cantidad' => 1, 'cant_pzas' => 1,
        'merma_id' => $merma->id,
    ]);
    $this->tarjeta = Tarjeta::factory()->create(['obra_id' => $this->obra->id]);
    $this->tarjeta->generadoras()->attach($this->gen->id);
    TarjetaRegistro::factory()->create([
        'tarjeta_id' => $this->tarjeta->id,
        'generadora_registro_id' => $this->reg->id,
        'insumo_id' => null,
        'cantidad' => null,
        'tipo_pintura' => 'auto',
    ]);
    ObraFleteViatico::factory()->create(['obra_id' => $this->obra->id, 'grupo' => 'VIATICOS', 'cantidad' => 2, 'p_unit' => 10]);
});

test('capturar guarda inputs y excluye cache/locks', function () {
    $snap = $this->vm->capturar($this->obra);

    expect($snap['generadoras'])->toHaveCount(1)
        ->and($snap['generadora_registros'])->toHaveCount(1)
        ->and($snap['tarjetas'])->toHaveCount(1)
        ->and($snap['tarjeta_registros'])->toHaveCount(1)
        ->and($snap['obra_fletes_viaticos'])->toHaveCount(1)
        ->and($snap['obra']['num_grupos'])->toBe($this->obra->num_grupos);

    // No se versionan cache ni locks.
    expect($snap['tarjetas'][0])->not->toHaveKeys(['importe_materiales', 'kilos_reales', 'locked_by', 'locked_at']);
});

test('restaurar reproduce los importes vía recálculo y remapea las FKs', function () {
    $importe1 = $this->calc->calcular($this->tarjeta)['total_importe']; // 60 kg × 100
    expect($importe1)->toEqualWithDelta(6000.0, 1e-6);

    $v1 = $this->vm->crear($this->obra, 'V1');

    // Mutaciones: cambia factor, borra el registro de generadora (cascada borra el de tarjeta).
    $this->obra->update(['factor_contratista' => 2.0]);
    $this->reg->delete();
    expect($this->obra->fresh()->tarjetas()->first()->registros()->count())->toBe(0);

    $this->vm->restaurar($v1);

    // La obra recupera su factor y su árbol; el importe se reproduce sobre la tarjeta restaurada.
    $obra = $this->obra->fresh();
    expect((float) $obra->factor_contratista)->toEqualWithDelta(1.15, 1e-9)
        ->and($obra->generadoras()->count())->toBe(1);

    $tarjetaRest = $obra->tarjetas()->first();
    $importe2 = $this->calc->calcular($tarjetaRest)['total_importe'];
    expect($importe2)->toEqualWithDelta($importe1, 1e-6);
});

test('restaurar es lineal: deja un respaldo automático y no ramifica', function () {
    $v1 = $this->vm->crear($this->obra, 'V1');
    $this->vm->restaurar($v1);

    // Tras restaurar: V1 + el respaldo auto = 2 versiones; el respaldo está marcado auto.
    expect(ObraVersion::where('obra_id', $this->obra->id)->count())->toBe(2)
        ->and(ObraVersion::where('obra_id', $this->obra->id)->where('auto', true)->count())->toBe(1);

    // El modelo no tiene noción de parentesco (historial lineal).
    expect(\Illuminate\Support\Facades\Schema::hasColumn('cotiz_obra_versiones', 'parent_version_id'))->toBeFalse();
});

test('comparar detecta cambios por grupo', function () {
    $antes = $this->vm->capturar($this->obra);
    ObraFleteViatico::factory()->create(['obra_id' => $this->obra->id, 'grupo' => 'FLETES', 'cantidad' => 1, 'p_unit' => 5]);
    $despues = $this->vm->capturar($this->obra);

    $diff = $this->vm->comparar($antes, $despues);

    expect($diff['grupos']['obra_fletes_viaticos']['de'])->toBe(1)
        ->and($diff['grupos']['obra_fletes_viaticos']['a'])->toBe(2)
        ->and($diff['grupos']['obra_fletes_viaticos']['cambio'])->toBeTrue()
        ->and($diff['grupos']['generadoras']['cambio'])->toBeFalse();
});
