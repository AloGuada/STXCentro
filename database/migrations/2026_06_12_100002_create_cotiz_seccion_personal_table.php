<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_seccion_personal', function (Blueprint $table) {
            // Matriz personal × fase: celdas con la cantidad de personas por (fase, categoría).
            $table->id();
            $table->foreignId('seccion_id')->constrained('cotiz_secciones_montaje')->cascadeOnDelete();
            $table->foreignId('fase_id')->constrained('cotiz_fases_montaje')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('cotiz_personal_categorias')->cascadeOnDelete();
            $table->unsignedInteger('cantidad')->default(0);
            $table->timestamps();

            $table->unique(['seccion_id', 'fase_id', 'categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_seccion_personal');
    }
};
