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
        Schema::create('costos_aprobaciones_solicitud', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('costos_solicitudes_pago')->cascadeOnDelete();
            $table->integer('nivel');
            $table->foreignUuid('aprobador_id')->nullable()->constrained('usuarios');
            $table->string('estatus')->default('pendiente');
            $table->timestamp('fecha_respuesta')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_aprobaciones_solicitud');
    }
};
