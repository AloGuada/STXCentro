<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remapea las requisiciones existentes al nuevo flujo de doble aprobación:
 * etapa interna (control gerencial) → `aprobada_interna` → etapa formal
 * (cadena de firmas) `pendiente_aprobacion`.
 *
 *   - `pendiente_aprobacion_interno` con cadena de firmas creada  → `pendiente_aprobacion`
 *   - `pendiente_aprobacion_interno` con control gerencial dado   → `aprobada_interna`
 *   - resto (sin control)                                          → se queda igual
 *
 * Debe correr ANTES de eliminar la columna `control_verificado`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tipo = 'App\\Models\\Costos\\Requisicion';

        // 1) Las que ya tienen cadena de firmas están en plena aprobación formal.
        $conCadena = DB::table('costos_aprobaciones')
            ->where('aprobable_type', $tipo)
            ->distinct()
            ->pluck('aprobable_id');

        if ($conCadena->isNotEmpty()) {
            DB::table('costos_requisiciones')
                ->where('estatus', 'pendiente_aprobacion_interno')
                ->whereIn('id', $conCadena)
                ->update(['estatus' => 'pendiente_aprobacion']);
        }

        // 2) Con aprobación interna (control) dada pero sin cadena → aprobada_interna.
        DB::table('costos_requisiciones')
            ->where('estatus', 'pendiente_aprobacion_interno')
            ->where('control_verificado', true)
            ->update(['estatus' => 'aprobada_interna']);

        // 3) El badge del gerente ya no depende del flag: estar en la etapa interna
        //    es la señal. Se quita la condición `control_verificado`.
        DB::table('badge_configs')
            ->where('tabla', 'costos_requisiciones')
            ->where('valor_estatus', 'pendiente_aprobacion_interno')
            ->update([
                'condiciones_extra' => json_encode([
                    ['tipo' => 'existe', 'tabla' => 'costos_requisicion_ocs', 'fk' => 'requisicion_id'],
                ]),
            ]);
    }

    public function down(): void
    {
        // Colapsa las dos etapas nuevas de vuelta al estado interno.
        DB::table('costos_requisiciones')
            ->whereIn('estatus', ['aprobada_interna', 'pendiente_aprobacion'])
            ->update(['estatus' => 'pendiente_aprobacion_interno']);
    }
};
