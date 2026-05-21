<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apartado temporal de presupuesto: cuando un documento (SolicitudPago o
 * Requisicion) entra a aprobación, su presupuesto se aparta por 5 días.
 * Si vence sin convertirse en permanente, se libera automáticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_rubros_afectados', function (Blueprint $table) {
            $table->date('apartado_hasta')->nullable()->after('estatus');
            $table->dateTime('vencido_at')->nullable()->after('apartado_hasta');

            $table->index('apartado_hasta');
            $table->index(['estatus', 'apartado_hasta']);
        });
    }

    public function down(): void
    {
        Schema::table('costos_rubros_afectados', function (Blueprint $table) {
            $table->dropIndex(['estatus', 'apartado_hasta']);
            $table->dropIndex(['apartado_hasta']);
            $table->dropColumn(['apartado_hasta', 'vencido_at']);
        });
    }
};
