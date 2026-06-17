<?php

use App\Models\Cob\Partida;
use App\Models\Costos\ObraRubro;
use App\Models\Obra;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convierte cada partida adicional (legacy `es_adicional`) en una sub-obra
     * real, re-apuntando su presupuesto (costos_obra_rubros) a la nueva obra
     * sin recrear rubros (preserva presupuestado/acumulado → invariante
     * neto-cero). La partida pasa a ser una partida normal de la sub-obra.
     *
     * Idempotente: el filtro `es_adicional = true` salta lo ya convertido.
     * One-way: down() es no-op (no se reconstruye el parche).
     */
    public function up(): void
    {
        Partida::query()
            ->where('es_adicional', true)
            ->with('obra:id,no,proyecto_id,cliente_id,tipo_contrato')
            ->chunkById(200, function ($partidas): void {
                foreach ($partidas as $partida) {
                    $padre = $partida->obra;
                    if ($padre === null) {
                        continue;
                    }

                    DB::transaction(function () use ($partida, $padre): void {
                        $sufijo = $partida->numero_adicional ?? $partida->id;

                        // Sub-obra sin disparar Obra::booted() (los obra_rubros ya existen).
                        $subObra = Obra::withoutEvents(fn () => Obra::create([
                            'proyecto_id' => $padre->proyecto_id,
                            'obra_padre_id' => $padre->id,
                            'tipo' => 'adicional',
                            'es_planta' => false,
                            'no' => $padre->no.'-ad'.$sufijo,
                            'descripcion' => $partida->descripcion,
                            'cliente_id' => $padre->cliente_id,
                            'tipo_contrato' => $padre->tipo_contrato,
                            'estatus' => $partida->estatus ?? 'abierta',
                            'activa' => ($partida->estatus ?? 'abierta') === 'abierta',
                        ]));

                        // Re-apuntar el presupuesto del adicional a la sub-obra.
                        ObraRubro::query()
                            ->where('adicional_partida_id', $partida->id)
                            ->update(['obra_id' => $subObra->id, 'adicional_partida_id' => null]);

                        // La partida queda como partida normal de la sub-obra.
                        // DB directo: el modelo ya no expone es_adicional/numero_adicional
                        // en fillable (parche retirado), un update Eloquent los ignoraría.
                        DB::table('cob_partidas')
                            ->where('id', $partida->id)
                            ->update([
                                'obra_id' => $subObra->id,
                                'es_adicional' => false,
                                'numero_adicional' => null,
                            ]);
                    });
                }
            });
    }

    public function down(): void
    {
        // Irreversible: la conversión adicional→sub-obra no se reconstruye.
    }
};
