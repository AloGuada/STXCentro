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
        Schema::table('costos_permisos', function (Blueprint $table) {
            // Si el nivel tiene este flag y el documento tiene presupuesto
            // reservado (apartado vigente), su aprobación se salta.
            $table->boolean('omitir_si_presupuesto_reservado')->default(false)->after('tipo_aprobacion');
        });
    }

    public function down(): void
    {
        Schema::table('costos_permisos', function (Blueprint $table) {
            $table->dropColumn('omitir_si_presupuesto_reservado');
        });
    }
};
