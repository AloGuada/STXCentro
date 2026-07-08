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
        Schema::table('costos_tipo_solicitud', function (Blueprint $table) {
            // Salta el primer nivel (verificación de costos) de la cadena de
            // aprobación en las solicitudes de pago de este tipo.
            $table->boolean('saltar_verificacion_costos')->default(false)->after('rubros');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_tipo_solicitud', function (Blueprint $table) {
            $table->dropColumn('saltar_verificacion_costos');
        });
    }
};
