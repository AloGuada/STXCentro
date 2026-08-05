<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El renglón liquidado se ancla en la pieza, no en la marca.
     *
     * `concepto_id` queda como referencia de conveniencia para navegar al
     * catálogo, y por eso pasa a ser opcional: lo que identifica al renglón es
     * `pieza_id` con su snapshot de qs, marca y etapa.
     */
    public function up(): void
    {
        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->unsignedBigInteger('concepto_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->unsignedBigInteger('concepto_id')->nullable(false)->change();
        });
    }
};
