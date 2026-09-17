<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La respuesta a cada punto del catálogo en una inspección.
     *
     * Era una columna por punto en `registros`. Aquí es una fila: el texto tal
     * como lo eligió el inspector (`valor_texto`), la medida si es número, y lo
     * que significa (`resultado`: cumple, no cumple, no aplica), que es lo que
     * cuenta el tablero sin tener que conocer cada forma de escribir «defecto».
     */
    public function up(): void
    {
        Schema::create('qal_inspeccion_puntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspeccion_id')->constrained('qal_inspecciones')->cascadeOnDelete();
            $table->foreignId('punto_id')->constrained('qal_puntos_inspeccion')->restrictOnDelete();
            $table->string('resultado', 10)->nullable();
            $table->decimal('valor_numerico', 12, 3)->nullable();
            $table->string('valor_texto', 160)->nullable();

            $table->unique(['inspeccion_id', 'punto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_inspeccion_puntos');
    }
};
