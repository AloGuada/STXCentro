<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('cob_documentos_estimacion');
    }
};
