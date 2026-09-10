<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los defectos marcados en una inspección, con cuántas veces aparecieron.
     *
     * Eran una cadena de texto (`Socavación:2; Porosidad / poros:1`) que se
     * partía al leer. Aquí es una fila por defecto, por id: renombrar uno en el
     * catálogo corrige también lo capturado. Sirve para la soldadura de 2ª y la
     * pintura de 3ª, porque el catálogo de defectos es uno solo.
     */
    public function up(): void
    {
        Schema::create('qal_inspeccion_defectos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspeccion_id')->constrained('qal_inspecciones')->cascadeOnDelete();
            $table->foreignId('defecto_id')->constrained('qal_defectos')->restrictOnDelete();
            $table->unsignedInteger('cantidad')->default(1);

            $table->unique(['inspeccion_id', 'defecto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_inspeccion_defectos');
    }
};
