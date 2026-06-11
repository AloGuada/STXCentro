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
        Schema::create('cotiz_tarjeta_estructuras', function (Blueprint $table) {
            // Columnas de la matriz de kilos reales por-tarjeta (ej. 'NAVE', 'NAVE B').
            $table->id();
            $table->foreignId('tarjeta_id')->constrained('cotiz_tarjetas')->cascadeOnDelete();
            $table->string('nombre');
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->index('tarjeta_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotiz_tarjeta_estructuras');
    }
};
