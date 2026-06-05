<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca la obra especial que representa el proyecto de planta (gasto
     * operativo de la planta física en costos). Solo puede existir una;
     * la unicidad se valida a nivel aplicación porque un índice único
     * parcial sobre boolean no es portable entre SQLite y PostgreSQL.
     */
    public function up(): void
    {
        Schema::table('obras', function (Blueprint $table) {
            $table->boolean('es_planta')->default(false)->after('activa');
        });
    }

    public function down(): void
    {
        Schema::table('obras', function (Blueprint $table) {
            $table->dropColumn('es_planta');
        });
    }
};
