<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // `monto_pagado` es un valor cacheado en la estimación que solo se mantiene
        // al registrar pagos desde la app. Estimaciones/pagos importados directo a
        // la BD quedaron con monto_pagado = 0 aunque tuvieran pagos. Se resincroniza
        // con la suma real de cob_estimaciones_pagos.
        DB::statement('
            UPDATE cob_estimaciones
            SET monto_pagado = COALESCE((
                SELECT SUM(p.monto_pagado)
                FROM cob_estimaciones_pagos p
                WHERE p.estimacion_id = cob_estimaciones.id
            ), 0)
        ');
    }

    public function down(): void
    {
        // No se revierte: el valor correcto es la suma real de pagos.
    }
};
