<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_obra_resumen_coeficientes', function (Blueprint $table) {
            // Override del coef_default de una fila del resumen, por obra (nivel fila).
            $table->id();
            $table->foreignId('obra_id')->constrained('cotiz_obras')->cascadeOnDelete();
            $table->foreignId('fila_id')->constrained('cotiz_resumen_filas')->cascadeOnDelete();
            $table->decimal('coef', 16, 6);
            $table->timestamps();

            $table->unique(['obra_id', 'fila_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_obra_resumen_coeficientes');
    }
};
