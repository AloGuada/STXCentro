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
        Schema::create('costos_configuracion', function (Blueprint $table) {
            $table->id();
            // Fila única (singleton) con parámetros editables del módulo de costos.
            $table->unsignedInteger('dias_apartado')->default(5);
            $table->unsignedInteger('dias_cancelar_requisicion')->default(10);
            $table->unsignedInteger('dias_cancelar_solicitud')->default(10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_configuracion');
    }
};
