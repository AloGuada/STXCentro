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
        Schema::table('costos_solicitudes_pago', function (Blueprint $table) {
            $table->foreignId('orden_compra_id')
                ->nullable()
                ->after('proveedor_id')
                ->constrained('costos_ordenes_compra')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_solicitudes_pago', function (Blueprint $table) {
            $table->dropForeign(['orden_compra_id']);
            $table->dropColumn('orden_compra_id');
        });
    }
};
