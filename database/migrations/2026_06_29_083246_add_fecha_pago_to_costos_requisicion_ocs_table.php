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
        Schema::table('costos_requisicion_ocs', function (Blueprint $table) {
            // Fecha de pago deseada del anticipo (solo contado); si es null, la
            // solicitud generada al liberar usa la fecha del día.
            $table->date('fecha_pago')->nullable()->after('fecha_entrega');
        });
    }

    public function down(): void
    {
        Schema::table('costos_requisicion_ocs', function (Blueprint $table) {
            $table->dropColumn('fecha_pago');
        });
    }
};
