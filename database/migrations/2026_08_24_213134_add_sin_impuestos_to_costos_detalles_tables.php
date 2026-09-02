<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Partidas que no causan impuestos. La clave genérica del SAT no distingue
     * qué producto es exento, así que Compras lo marca a mano en el tab de
     * cotización y la marca viaja a la orden de compra.
     */
    public function up(): void
    {
        Schema::table('costos_requisicion_detalle', function (Blueprint $table) {
            $table->boolean('sin_impuestos')->default(false)->after('tipo_fiscal');
        });

        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->boolean('sin_impuestos')->default(false)->after('tipo_fiscal');
        });
    }

    public function down(): void
    {
        Schema::table('costos_requisicion_detalle', function (Blueprint $table) {
            $table->dropColumn('sin_impuestos');
        });

        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->dropColumn('sin_impuestos');
        });
    }
};
