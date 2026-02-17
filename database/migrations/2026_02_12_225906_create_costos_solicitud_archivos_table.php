<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('costos_solicitud_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('costos_solicitudes_pago')->cascadeOnDelete();
            $table->foreignId('archivo_id')->constrained('costos_documentos');
            $table->string('ruta_archivo');
            $table->string('nombre_original');
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_solicitud_archivos');
    }
};
