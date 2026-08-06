<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Planta cambió el layout: la columna ETAPA ahora se llama LOTE.
     *
     * Es el mismo dato con otro nombre —el segundo nivel de identidad del
     * modelo, junto con la marca—, así que se renombra la columna en vez de
     * crear una nueva: el histórico se conserva y las marcas que hoy se
     * distinguen por etapa siguen distinguiéndose por lote.
     *
     * El unique se tira y se vuelve a crear para que no quede nombrado con la
     * palabra vieja, y el snapshot de la liquidación se renombra igual para que
     * lo pagado se lea con el mismo vocabulario que el catálogo.
     */
    public function up(): void
    {
        Schema::table('conceptos', function (Blueprint $table) {
            $table->dropUnique(['catalogo_id', 'marca', 'etapa']);
        });

        Schema::table('conceptos', function (Blueprint $table) {
            $table->renameColumn('etapa', 'lote');
        });

        Schema::table('conceptos', function (Blueprint $table) {
            $table->unique(['catalogo_id', 'marca', 'lote']);
        });

        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->renameColumn('etapa', 'lote');
        });
    }

    public function down(): void
    {
        Schema::table('conceptos', function (Blueprint $table) {
            $table->dropUnique(['catalogo_id', 'marca', 'lote']);
        });

        Schema::table('conceptos', function (Blueprint $table) {
            $table->renameColumn('lote', 'etapa');
        });

        Schema::table('conceptos', function (Blueprint $table) {
            $table->unique(['catalogo_id', 'marca', 'etapa']);
        });

        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->renameColumn('lote', 'etapa');
        });
    }
};
