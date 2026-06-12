<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_seccion_fase_rendimiento', function (Blueprint $table) {
            // Detalle de rendimientos por fase (filas tipo "Columnas Principales 3PLS × 18 pzas × 10 pzas/día").
            $table->id();
            $table->foreignId('seccion_id')->constrained('cotiz_secciones_montaje')->cascadeOnDelete();
            $table->foreignId('fase_id')->constrained('cotiz_fases_montaje')->cascadeOnDelete();
            $table->string('concepto');
            $table->string('largo_pza')->nullable();
            $table->decimal('cantidad', 16, 4)->default(0);
            $table->decimal('rendimiento', 16, 4)->default(0);
            $table->decimal('jornales', 16, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_seccion_fase_rendimiento');
    }
};
