<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_obra_resumen_celda_override', function (Blueprint $table) {
            // Override granular del coef por celda (obra, fila, columna). Gana sobre el override de fila.
            $table->id();
            $table->foreignId('obra_id')->constrained('cotiz_obras')->cascadeOnDelete();
            $table->foreignId('fila_id')->constrained('cotiz_resumen_filas')->cascadeOnDelete();
            $table->foreignId('columna_id')->constrained('cotiz_resumen_columnas')->cascadeOnDelete();
            $table->decimal('coef', 16, 6);
            $table->timestamps();

            $table->unique(['obra_id', 'fila_id', 'columna_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_obra_resumen_celda_override');
    }
};
