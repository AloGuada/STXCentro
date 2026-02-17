<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_solicitud_id')->constrained('costos_tipo_solicitud')->cascadeOnDelete();
            $table->string('titulo');
            $table->boolean('multiple')->default(false);
            $table->text('texto')->nullable();
            $table->text('texto_adicional')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_documentos');
    }
};
