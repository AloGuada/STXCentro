<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_obra_cuadrilla_global', function (Blueprint $table) {
            // Composición de personas por categoría POR GRUPO. El número de grupos vive en cotiz_obras.num_grupos.
            $table->id();
            $table->foreignId('obra_id')->constrained('cotiz_obras')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('cotiz_personal_categorias')->cascadeOnDelete();
            $table->unsignedInteger('cantidad_por_grupo')->default(0);
            $table->timestamps();

            $table->unique(['obra_id', 'categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_obra_cuadrilla_global');
    }
};
