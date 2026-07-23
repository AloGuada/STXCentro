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
        foreach (['costos_requisiciones', 'costos_solicitudes_pago', 'costos_ordenes_compra'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->decimal('tipo_cambio', 14, 6)->default(1)->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['costos_requisiciones', 'costos_solicitudes_pago', 'costos_ordenes_compra'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('tipo_cambio');
            });
        }
    }
};
