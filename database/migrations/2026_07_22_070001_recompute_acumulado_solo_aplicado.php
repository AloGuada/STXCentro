<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Separa el `acumulado` (ejercido) del `apartado` (comprometido vivo).
 *
 * Hasta ahora `acumulado` incluía tanto los RubroAfectado `aplicado` como los
 * `apartado`. Tras el refactor:
 *   - `acumulado` = Σ monto de rubros_afectados con estatus 'aplicado'.
 *   - `apartado`  = Σ monto de rubros_afectados con estatus 'apartado'.
 *
 * Idempotente: al segundo pase `acumulado` ya coincide con la suma de aplicados
 * y no se registra ningún movimiento de ajuste.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $rubros = DB::table('costos_obra_rubros')->select('id', 'acumulado')->get();

            foreach ($rubros as $rubro) {
                $aplicado = (float) DB::table('costos_rubros_afectados')
                    ->where('obra_rubro_id', $rubro->id)
                    ->where('estatus', 'aplicado')
                    ->sum('monto');

                $apartado = (float) DB::table('costos_rubros_afectados')
                    ->where('obra_rubro_id', $rubro->id)
                    ->where('estatus', 'apartado')
                    ->sum('monto');

                $antes = (float) $rubro->acumulado;

                DB::table('costos_obra_rubros')
                    ->where('id', $rubro->id)
                    ->update(['apartado' => $apartado]);

                if (abs($antes - $aplicado) < 0.005) {
                    continue;
                }

                DB::table('costos_obra_rubros')
                    ->where('id', $rubro->id)
                    ->update(['acumulado' => $aplicado]);

                $delta = $aplicado - $antes;
                DB::table('costos_rubro_movimientos')->insert([
                    'obra_rubro_id' => $rubro->id,
                    'rubro_afectado_id' => null,
                    'tipo' => $delta >= 0 ? 'cargo' : 'reverso',
                    'monto' => abs($delta),
                    'saldo_antes' => $antes,
                    'saldo_despues' => $aplicado,
                    'motivo' => 'ajuste refactor: acumulado = solo ejercido',
                    'usuario_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // No reversible: recompone acumulado = ejercido + apartado.
        DB::transaction(function () {
            $rubros = DB::table('costos_obra_rubros')->select('id', 'acumulado', 'apartado')->get();

            foreach ($rubros as $rubro) {
                DB::table('costos_obra_rubros')
                    ->where('id', $rubro->id)
                    ->update(['acumulado' => (float) $rubro->acumulado + (float) $rubro->apartado]);
            }
        });
    }
};
