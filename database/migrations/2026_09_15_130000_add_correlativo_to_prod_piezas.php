<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El correlativo de la pieza: la numeración que planta le da según su QR.
     * Es texto («1 de 92»): el «de N» es el tamaño del grupo en que planta
     * numeró, que no coincide con el modelo, y se guarda como lo imprimen.
     *
     * Llega en el layout nuevo, que a la vez deja de traer QS. El QS se queda
     * en la tabla como historia de las piezas viejas y porque el CSV de avance
     * todavía lo usa para emparejar cuando no trae QR.
     */
    public function up(): void
    {
        Schema::table('prod_piezas', function (Blueprint $table) {
            $table->string('correlativo', 30)->nullable()->after('qs');
        });
    }

    public function down(): void
    {
        Schema::table('prod_piezas', function (Blueprint $table) {
            $table->dropColumn('correlativo');
        });
    }
};
