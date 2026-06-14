<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_obra_rubros', function (Blueprint $table) {
            $table->foreignId('adicional_partida_id')
                ->nullable()
                ->after('obra_id')
                ->constrained('cob_partidas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('costos_obra_rubros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adicional_partida_id');
        });
    }
};
