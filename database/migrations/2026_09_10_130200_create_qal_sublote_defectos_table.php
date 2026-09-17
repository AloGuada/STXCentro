<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Por qué se rechazó cada unidad de la muestra.
     *
     * Una unidad puede fallar por varias familias a la vez (soldadura,
     * dimensional, barrenos, limpieza), así que va una fila por unidad y
     * defecto. `unidad` es su número dentro de las rechazadas del sublote.
     */
    public function up(): void
    {
        Schema::create('qal_sublote_defectos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sublote_id')->constrained('qal_sublotes')->cascadeOnDelete();
            $table->unsignedSmallInteger('unidad');
            $table->foreignId('defecto_id')->constrained('qal_defectos')->restrictOnDelete();

            $table->unique(['sublote_id', 'unidad', 'defecto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_sublote_defectos');
    }
};
