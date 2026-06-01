<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_requisicion_detalle', function (Blueprint $table) {
            $table->foreignId('obra_rubro_id')
                ->nullable()
                ->after('requisicion_id')
                ->constrained('costos_obra_rubros')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('costos_requisicion_detalle', function (Blueprint $table) {
            $table->dropConstrainedForeignId('obra_rubro_id');
        });
    }
};
