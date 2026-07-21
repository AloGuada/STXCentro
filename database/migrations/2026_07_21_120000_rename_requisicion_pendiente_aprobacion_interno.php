<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El estado `pendiente_aprobacion` de las requisiciones se renombra a
     * `pendiente_aprobacion_interno`. Se actualizan las filas existentes y la
     * configuración de badge que apuntaba al valor viejo (valor y filtro).
     */
    public function up(): void
    {
        DB::table('costos_requisiciones')
            ->where('estatus', 'pendiente_aprobacion')
            ->update(['estatus' => 'pendiente_aprobacion_interno']);

        DB::table('badge_configs')
            ->where('tabla', 'costos_requisiciones')
            ->where('valor_estatus', 'pendiente_aprobacion')
            ->update([
                'valor_estatus' => 'pendiente_aprobacion_interno',
                'filter_href' => '/admin/costos/requisiciones?estatus=pendiente_aprobacion_interno',
            ]);
    }

    public function down(): void
    {
        DB::table('costos_requisiciones')
            ->where('estatus', 'pendiente_aprobacion_interno')
            ->update(['estatus' => 'pendiente_aprobacion']);

        DB::table('badge_configs')
            ->where('tabla', 'costos_requisiciones')
            ->where('valor_estatus', 'pendiente_aprobacion_interno')
            ->update([
                'valor_estatus' => 'pendiente_aprobacion',
                'filter_href' => '/admin/costos/requisiciones?estatus=pendiente_aprobacion',
            ]);
    }
};
