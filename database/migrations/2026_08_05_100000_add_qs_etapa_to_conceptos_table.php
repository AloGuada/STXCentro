<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La marca deja de identificar por sí sola a la pieza.
     *
     * Un mismo catálogo puede repetir la marca en etapas distintas de la obra, así
     * que el modelo lo denota el par (marca, etapa). `qs` es el id que trae el
     * layout del sistema de planta: se guarda como dato de consulta y trazabilidad,
     * no participa en la identidad.
     *
     * Las piezas que ya existen quedan con etapa nula, que es una etapa más: el par
     * (marca, null) sigue siendo único dentro de su catálogo.
     */
    public function up(): void
    {
        Schema::table('conceptos', function (Blueprint $table) {
            $table->string('qs', 50)->nullable()->after('id');
            $table->string('etapa', 50)->nullable()->after('marca');
            $table->index(['catalogo_id', 'marca', 'etapa']);
        });

        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->string('etapa', 50)->nullable()->after('marca');
        });
    }

    public function down(): void
    {
        Schema::table('conceptos', function (Blueprint $table) {
            $table->dropIndex(['catalogo_id', 'marca', 'etapa']);
            $table->dropColumn(['qs', 'etapa']);
        });

        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->dropColumn('etapa');
        });
    }
};
