<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cob_documento_seccion_proyecto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('seccion_id')->constrained('cob_documento_secciones')->cascadeOnDelete();
            $table->string('estatus')->default('pendiente');
            $table->timestamps();

            $table->unique(['proyecto_id', 'seccion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_documento_seccion_proyecto');
    }
};
