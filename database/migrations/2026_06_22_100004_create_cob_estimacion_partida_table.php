<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partidas que cubre una estimación de nivel 'partida'. Solo asociación
 * (sin monto por línea): el monto sigue en la estimación. Las partidas
 * pertenecen a la obra de la estimación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cob_estimacion_partida', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimacion_id')->constrained('cob_estimaciones')->cascadeOnDelete();
            $table->foreignId('partida_id')->constrained('cob_partidas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['estimacion_id', 'partida_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_estimacion_partida');
    }
};
