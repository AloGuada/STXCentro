<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('costos_aprobacion_departamento', function (Blueprint $table) {
            $table->boolean('omitir_si_presupuesto_reservado')->default(false)->after('aprobador_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_aprobacion_departamento', function (Blueprint $table) {
            $table->dropColumn('omitir_si_presupuesto_reservado');
        });
    }
};
