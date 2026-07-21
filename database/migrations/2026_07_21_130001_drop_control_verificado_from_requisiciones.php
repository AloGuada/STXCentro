<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina el flag redundante `control_verificado`: con el flujo de doble
 * aprobación, estar en `aprobada_interna` ES la señal de "control gerencial
 * dado". Se conservan `control_por` / `control_at` como auditoría de quién y
 * cuándo dio la aprobación interna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->dropColumn('control_verificado');
        });
    }

    public function down(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->boolean('control_verificado')->default(false)->after('estatus');
        });
    }
};
