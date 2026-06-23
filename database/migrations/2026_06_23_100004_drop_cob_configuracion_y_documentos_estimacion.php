<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Feature huérfana (checklist de documentos en estimaciones) que nunca se
        // construyó: 0 registros, sin rutas ni UI. Se elimina por completo.
        Schema::dropIfExists('cob_documentos_estimacion');
        Schema::dropIfExists('cob_configuracion_documentos');
    }

    public function down(): void
    {
        Schema::create('cob_configuracion_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->string('nombre_documento');
            $table->string('descripcion')->nullable();
            $table->boolean('obligatorio')->default(false);
            $table->string('estatus')->default('pendiente');
            $table->timestamps();
        });

        Schema::create('cob_documentos_estimacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimacion_id')->constrained('cob_estimaciones')->cascadeOnDelete();
            $table->foreignId('configuracion_documento_id')->constrained('cob_configuracion_documentos');
            $table->string('ruta_archivo');
            $table->timestamp('fecha_subida')->nullable();
            $table->foreignUuid('subido_por')->constrained('usuarios');
            $table->timestamps();
        });
    }
};
