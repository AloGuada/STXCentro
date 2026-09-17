<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los espesores de pintura de una inspección de 3ª (SSPC-PA2).
     *
     * El promedio y si cumple se calculan de las lecturas y se guardan porque
     * es lo que imprime el formato. `mediciones_bajas` son las mediciones por
     * debajo del 80 % del requerido: avisan, no rechazan.
     *
     * La revisión (R1/R2/R3) y la acción (A, R, RM) son del bloque de pintura
     * del formato y viven aquí.
     */
    public function up(): void
    {
        Schema::create('qal_pintura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspeccion_id')->unique()->constrained('qal_inspecciones')->cascadeOnDelete();
            $table->decimal('espesor_requerido_mils', 8, 2)->nullable();
            $table->string('metodo', 60)->nullable();
            $table->decimal('area_m2', 10, 2)->nullable();
            $table->unsignedTinyInteger('mediciones_visibles')->default(5);
            $table->decimal('promedio_mils', 8, 2)->nullable();
            $table->boolean('cumple')->nullable();
            $table->json('mediciones_bajas')->nullable();
            $table->string('revision', 3)->nullable();
            $table->string('accion', 3)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_pintura');
    }
};
