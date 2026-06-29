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
        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->foreignId('producto_id')->nullable()->after('id')->constrained('costos_productos')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_id');
        });
    }
};
