<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Línea y módulo de la pieza, tal como los trae el layout de planta. Son
     * datos de la pieza física —dónde se fabrica y a qué módulo de la obra
     * va—, no del modelo, así que cuelgan aquí y no de la marca. Calidad los
     * captura en cada inspección; con esto los precarga al escanear.
     */
    public function up(): void
    {
        Schema::table('prod_piezas', function (Blueprint $table) {
            $table->string('linea', 10)->nullable()->after('correlativo');
            $table->string('modulo', 60)->nullable()->after('linea');
        });
    }

    public function down(): void
    {
        Schema::table('prod_piezas', function (Blueprint $table) {
            $table->dropColumn(['linea', 'modulo']);
        });
    }
};
