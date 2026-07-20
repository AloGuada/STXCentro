<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca una partida como "solo cotización": se cotiza como referencia (ej. un
     * envío de cantidad variable) y suma al total de captura, pero no se adjudica
     * a proveedor, no aparece en el comparativo/PDF ni afecta el neto a pagar.
     */
    public function up(): void
    {
        Schema::table('costos_requisicion_detalle', function (Blueprint $table) {
            $table->boolean('solo_cotizacion')->default(false)->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('costos_requisicion_detalle', function (Blueprint $table) {
            $table->dropColumn('solo_cotizacion');
        });
    }
};
