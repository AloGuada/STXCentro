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

    // Presupuesto propio de la base. En producción, al correr la migración, lo
    // habría generado el hook booted (ya retirado); aquí se siembra explícito.
    foreach (Rubro::factory()->count(2)->create(['ambito' => 'obra']) as $r) {
        ObraRubro::create(['obra_id' => $base->id, 'rubro_id' => $r->id, 'presupuestado' => 0, 'acumulado' => 0]);
    }

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

    $sub = Obra::where('proyecto_id', $proyecto->id)->where('tipo', 'adicional')->firstOrFail();
    expect($sub->tipo)->toBe('adicional')
        ->and($sub->proyecto_id)->toBe($proyecto->id)
        ->and($sub->no)->toBe('OB-9-ad1');

    // Presupuesto re-apuntado a la sub-obra, conservando montos (neto-cero).
    expect($sub->obraRubros()->count())->toBe(2)
        ->and((float) $sub->obraRubros()->sum('presupuestado'))->toBe(1000.0)
        ->and((float) $sub->obraRubros()->sum('acumulado'))->toBe(200.0);

    // La partida queda como partida normal de la sub-obra.
    $partida = DB::table('cob_partidas')->where('id', $partidaId)->first();
    expect((int) $partida->obra_id)->toBe($sub->id)
        ->and((bool) $partida->es_adicional)->toBeFalse();

    // La obra base conserva su propio presupuesto (los rubros sembrados arriba).
    expect($base->obraRubros()->count())->toBe(2);

    // Ya no quedan obra_rubros con adicional_partida_id.
    expect(ObraRubro::whereNotNull('adicional_partida_id')->count())->toBe(0);
});

it('convierte multiples adicionales de una obra en sub-obras independientes', function () {
    $proyecto = Proyecto::factory()->create();
    $base = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base', 'no' => 'OB-50']);

    // 3 adicionales, cada uno con su propio presupuesto (rubro distinto).
    $adicionales = collect([1, 2, 3])->map(function (int $n) use ($base) {
        $partidaId = insertarPartidaAdicional($base->id, $n, 'suministro', $n * 1000);
        $rubro = Rubro::factory()->create(['ambito' => 'obra']);
        $or = ObraRubro::create([
            'obra_id' => $base->id,
            'rubro_id' => $rubro->id,
            'presupuestado' => $n * 100,
            'acumulado' => $n * 10,
        ]);
        DB::table('costos_obra_rubros')->where('id', $or->id)->update(['adicional_partida_id' => $partidaId]);

        return ['n' => $n, 'partidaId' => $partidaId];
    });

    correrConversion();

    // 3 adicionales → 3 sub-obras (hermanas), con sufijos distintos.
    $subObras = Obra::where('proyecto_id', $proyecto->id)->where('tipo', 'adicional');
    expect($subObras->count())->toBe(3)
        ->and($subObras->pluck('no')->sort()->values()->all())
        ->toBe(['OB-50-ad1', 'OB-50-ad2', 'OB-50-ad3']);

    // Cada sub-obra recibe SOLO su propio presupuesto (no se cruzan).
    foreach ($adicionales as $ad) {
        $sub = Obra::where('no', 'OB-50-ad'.$ad['n'])->firstOrFail();
        expect($sub->obraRubros()->count())->toBe(1)
            ->and((float) $sub->obraRubros()->sum('presupuestado'))->toBe((float) ($ad['n'] * 100))
            ->and((float) $sub->obraRubros()->sum('acumulado'))->toBe((float) ($ad['n'] * 10));

        $partida = DB::table('cob_partidas')->where('id', $ad['partidaId'])->first();
        expect((int) $partida->obra_id)->toBe($sub->id)
            ->and((bool) $partida->es_adicional)->toBeFalse();
    }

    // Ningun obra_rubro quedo huerfano con adicional_partida_id.
    expect(ObraRubro::whereNotNull('adicional_partida_id')->count())->toBe(0);
});

it('aisla los adicionales por obra cuando varias obras tienen adicionales', function () {
    $proyecto = Proyecto::factory()->create();
    $obraA = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base', 'no' => 'OB-A']);
    $obraB = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base', 'no' => 'OB-B']);

    foreach ([$obraA, $obraB] as $obra) {
        foreach ([1, 2] as $n) {
            $partidaId = insertarPartidaAdicional($obra->id, $n);
            $or = ObraRubro::create(['obra_id' => $obra->id, 'rubro_id' => Rubro::factory()->create(['ambito' => 'obra'])->id, 'presupuestado' => 500, 'acumulado' => 0]);
            DB::table('costos_obra_rubros')->where('id', $or->id)->update(['adicional_partida_id' => $partidaId]);
        }
    }

    correrConversion();

    // Cada obra base genera exactamente sus 2 sub-obras; el nombre conserva el
    // origen (ya no hay vínculo padre-hijo: son hermanas en el proyecto).
    expect(Obra::where('no', 'like', 'OB-A-ad%')->count())->toBe(2)
        ->and(Obra::where('no', 'like', 'OB-B-ad%')->count())->toBe(2)
        ->and(Obra::where('no', 'like', 'OB-A-ad%')->pluck('no')->sort()->values()->all())->toBe(['OB-A-ad1', 'OB-A-ad2'])
        ->and(Obra::where('no', 'like', 'OB-B-ad%')->pluck('no')->sort()->values()->all())->toBe(['OB-B-ad1', 'OB-B-ad2']);
});

it('es idempotente con multiples adicionales (re-correr no duplica)', function () {
    $proyecto = Proyecto::factory()->create();
    $base = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base', 'no' => 'OB-77']);

    foreach ([1, 2, 3] as $n) {
        $partidaId = insertarPartidaAdicional($base->id, $n);
        $or = ObraRubro::create(['obra_id' => $base->id, 'rubro_id' => Rubro::factory()->create(['ambito' => 'obra'])->id, 'presupuestado' => 100, 'acumulado' => 0]);
        DB::table('costos_obra_rubros')->where('id', $or->id)->update(['adicional_partida_id' => $partidaId]);
    }

    correrConversion();
    correrConversion();

    expect(Obra::where('proyecto_id', $proyecto->id)->where('tipo', 'adicional')->count())->toBe(3);
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

    expect(Obra::where('proyecto_id', $proyecto->id)->where('tipo', 'adicional')->count())->toBe(1);
});
