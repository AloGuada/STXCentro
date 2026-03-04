<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_persona_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('rh_personas')->cascadeOnDelete();
            $table->string('tipo_documento');
            $table->string('nombre_archivo');
            $table->string('ruta_archivo');
            $table->string('extension')->nullable();
            $table->unsignedBigInteger('tamano')->nullable();
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vigencia')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_persona_documentos');
    }
};
