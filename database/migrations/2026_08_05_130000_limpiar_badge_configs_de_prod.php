<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Desactiva los badges del sidebar que apuntan a columnas que este refactor
     * eliminó.
     *
     * `badge_configs` referencia tabla y campo **por dato**, así que ningún
     * rename los alcanza: un badge que siga consultando `prod_registros.cantidad`
     * revienta con 500 toda página que dibuje el sidebar, y eso no lo atrapa
     * ningún test. Se desactivan en vez de borrarse para no perder la
     * configuración de quien quiera reapuntarla a mano.
     */
    public function up(): void
    {
        if (! Schema::hasTable('badge_configs')) {
            return;
        }

        $columnasIdas = [
            'prod_registros' => ['concepto_id', 'cantidad'],
            'prod_liquidacion_detalle' => ['cantidad'],
            'prod_grupos_precio' => ['precio_kilo'],
            'conceptos' => ['qs'],
        ];

        foreach ($columnasIdas as $tabla => $campos) {
            DB::table('badge_configs')
                ->where('tabla', $tabla)
                ->whereIn('campo_estatus', $campos)
                ->update(['activo' => false]);
        }
    }

    /**
     * Sin vuelta atrás: reactivarlos volvería a romper el sidebar.
     */
    public function down(): void {}
};
