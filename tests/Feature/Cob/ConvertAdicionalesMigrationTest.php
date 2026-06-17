<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Rubro;
use App\Models\Obra;
use App\Models\Proyecto;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function correrConversion(): void
{
    (require database_path('migrations/2026_06_17_152532_convert_adicionales_to_subobras.php'))->up();
}

/**
 * El parche legacy (es_adicional / numero_adicional / adicional_partida_id) ya fue
 * retirado del esquema por las migraciones de drop de la Fase 5, así que aquí lo
 * reconstruimos para poder ejercitar la migración de conversión sobre el estado
 * previo que tendría producción al momento de correrla.
 */
function reconstruirParcheLegacy(): void
{
    Schema::table('cob_partidas', function (Blueprint $table): void {
        $table->boolean('es_adicional')->default(false);
        $table->unsignedInteger('numero_adicional')->nullable();
        $table->boolean('es_subobra')->default(false);
    });

    Schema::table('costos_obra_rubros', function (Blueprint $table): void {
        $table->unsignedBigInteger('adicional_partida_id')->nullable();
    });
}

/** @return int id de la partida adicional insertada */
function insertarPartidaAdicional(int $obraId, int $numeroAdicional, string $tipo = 'suministro', float $monto = 1000): int
{
    return DB::table('cob_partidas')->insertGetId([
        'obra_id' => $obraId,
        'tipo' => $tipo,
        'es_adicional' => true,
        'numero_adicional' => $numeroAdicional,
        'es_subobra' => false,
        'estatus' => 'abierta',
        'descripcion' => 'Adicional '.$numeroAdicional,
        'monto' => $monto,
        'moneda' => 'MXN',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function (): void {
    reconstruirParcheLegacy();
});

it('convierte una partida adicional en sub-obra y re-apunta su presupuesto', function () {
    $rubros = Rubro::factory()->count(2)->create(['ambito' => 'obra']);
    $proyecto = Proyecto::factory()->create();
    $base = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base', 'no' => 'OB-9']);

    // Estado legacy: partida adicional + sus obra_rubros con adicional_partida_id.
    $partidaId = insertarPartidaAdicional($base->id, 1);
    foreach ($rubros as $r) {
        $or = ObraRubro::create([
            'obra_id' => $base->id,
            'rubro_id' => $r->id,
            'presupuestado' => 500,
            'acumulado' => 100,
        ]);
        DB::table('costos_obra_rubros')->where('id', $or->id)->update(['adicional_partida_id' => $partidaId]);
    }

    correrConversion();

    $sub = $base->subObras()->firstOrFail();
    expect($sub->tipo)->toBe('adicional')
        ->and($sub->proyecto_id)->toBe($proyecto->id)
        ->and($sub->obra_padre_id)->toBe($base->id)
        ->and($sub->no)->toBe('OB-9-ad1');

    // Presupuesto re-apuntado a la sub-obra, conservando montos (neto-cero).
    expect($sub->obraRubros()->count())->toBe(2)
        ->and((float) $sub->obraRubros()->sum('presupuestado'))->toBe(1000.0)
        ->and((float) $sub->obraRubros()->sum('acumulado'))->toBe(200.0);

    // La partida queda como partida normal de la sub-obra.
    $partida = DB::table('cob_partidas')->where('id', $partidaId)->first();
    expect((int) $partida->obra_id)->toBe($sub->id)
        ->and((bool) $partida->es_adicional)->toBeFalse();

    // La obra base conserva su propio presupuesto (los rubros del booted).
    expect($base->obraRubros()->count())->toBe(2);

    // Ya no quedan obra_rubros con adicional_partida_id.
    expect(ObraRubro::whereNotNull('adicional_partida_id')->count())->toBe(0);
});

it('es idempotente (re-correr no duplica sub-obras)', function () {
    Rubro::factory()->create(['ambito' => 'obra']);
    $proyecto = Proyecto::factory()->create();
    $base = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base']);

    $partidaId = insertarPartidaAdicional($base->id, 1, 'montaje', 0);
    $or = ObraRubro::create(['obra_id' => $base->id, 'rubro_id' => Rubro::first()->id, 'presupuestado' => 0, 'acumulado' => 0]);
    DB::table('costos_obra_rubros')->where('id', $or->id)->update(['adicional_partida_id' => $partidaId]);

    correrConversion();
    correrConversion();

    expect($base->subObras()->count())->toBe(1);
});
