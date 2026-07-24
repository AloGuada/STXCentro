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
        Schema::table('costos_permisos', function (Blueprint $table) {
            $table->boolean('es_costos')->default(false)->after('omitir_si_presupuesto_reservado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_permisos', function (Blueprint $table) {
            $table->dropColumn('es_costos');
        });
    }
};
