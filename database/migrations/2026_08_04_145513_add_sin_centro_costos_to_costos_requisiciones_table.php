<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requisición "sin obra": no carga a ningún centro de costos. Sus partidas van
 * con `obra_rubro_id` nulo y el documento nunca genera RubroAfectado, ni al
 * apartar ni al liberarse a orden de compra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->boolean('sin_centro_costos')->default(false)->after('presupuesto_id');
        });
    }

    public function down(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->dropColumn('sin_centro_costos');
        });
    }
};
