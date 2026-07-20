<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Se elimina el estado `cotizada`: la cotización ahora ocurre en `borrador`.
     * Las requisiciones que quedaron en `cotizada` regresan a `borrador` y se
     * les limpia la aprobación gerencial (que ahora vive en `pendiente_aprobacion`).
     */
    public function up(): void
    {
        DB::table('costos_requisiciones')
            ->where('estatus', 'cotizada')
            ->update([
                'estatus' => 'borrador',
                'control_verificado' => false,
                'control_por' => null,
                'control_at' => null,
            ]);
    }

    public function down(): void
    {
        // Irreversible: no se puede distinguir cuáles borradores eran cotizadas.
    }
};
