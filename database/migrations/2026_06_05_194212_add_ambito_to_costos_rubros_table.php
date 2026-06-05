<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ámbito del centro de costos: 'obra' (presupuesto por obra) o
     * 'planta' (gasto operativo del proyecto de planta). String en vez
     * de enum nativo por portabilidad SQLite/PostgreSQL; el conjunto
     * válido se controla por validación.
     */
    public function up(): void
    {
        Schema::table('costos_rubros', function (Blueprint $table) {
            $table->string('ambito')->default('obra')->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('costos_rubros', function (Blueprint $table) {
            $table->dropColumn('ambito');
        });
    }
};
