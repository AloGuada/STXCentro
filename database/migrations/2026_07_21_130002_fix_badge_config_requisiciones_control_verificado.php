<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Limpia el badge de requisiciones que quedó apuntando a data obsoleta: quita la
 * condición `control_verificado` (columna ya eliminada) y normaliza el
 * `valor_estatus` legado 'cotizada' → 'pendiente_aprobacion_interno'.
 *
 * El remapeo previo solo tocó filas con valor_estatus='pendiente_aprobacion_interno',
 * dejando fuera las que aún tenían 'cotizada'. Sin esto, computeBadgeCount consulta
 * una columna inexistente y revienta con 500 toda página con sidebar.
 *
 * Idempotente: sin condiciones control_verificado ni valor_estatus 'cotizada' es no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('badge_configs')->where('tabla', 'costos_requisiciones')->get();

        foreach ($rows as $row) {
            $updates = [];

            $extra = $row->condiciones_extra ? json_decode($row->condiciones_extra, true) : null;
            if (is_array($extra)) {
                $limpio = array_values(array_filter(
                    $extra,
                    fn ($c) => ($c['campo'] ?? null) !== 'control_verificado',
                ));

                if ($limpio !== $extra) {
                    $updates['condiciones_extra'] = json_encode($limpio);
                }
            }

            if ($row->valor_estatus === 'cotizada') {
                $updates['valor_estatus'] = 'pendiente_aprobacion_interno';
            }

            if ($updates !== []) {
                DB::table('badge_configs')->where('id', $row->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        // Limpieza de datos: no se revierte.
    }
};
