<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Defectos de pintura que puede marcar el inspector de 3ª transformación.
     *
     * Misma advertencia que en soldadura: renombrar uno no reescribe los
     * registros ya guardados.
     */
    public function up(): void
    {
        Schema::create('qal_defectos_pintura', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_defectos_pintura');
    }
};
