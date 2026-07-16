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
        Schema::table('costos_configuracion', function (Blueprint $table) {
            $table->foreignUuid('gerente_compras_id')
                ->nullable()
                ->after('corte_hora')
                ->constrained('usuarios')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_configuracion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gerente_compras_id');
        });
    }
};
