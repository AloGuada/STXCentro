<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad: liga cada OC y cada partida de OC a su origen en requisicion.
 * Ambos FKs son nullable para no romper OCs creadas directamente sin
 * requisicion (camino legacy todavia soportado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->foreignId('requisicion_id')
                ->nullable()
                ->after('referencia')
                ->constrained('costos_requisiciones')
                ->nullOnDelete();
        });

        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->foreignId('requisicion_detalle_id')
                ->nullable()
                ->after('orden_compra_id')
                ->constrained('costos_requisicion_detalle')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->dropForeign(['requisicion_detalle_id']);
            $table->dropColumn('requisicion_detalle_id');
        });

        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->dropForeign(['requisicion_id']);
            $table->dropColumn('requisicion_id');
        });
    }
};
