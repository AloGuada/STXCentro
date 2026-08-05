<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qué procesos se pagan en cada obra. Una obra que sólo suelda no debe ver
     * pintura en la captura ni pedirle tarifa al grupo de precios.
     */
    public function up(): void
    {
        Schema::create('prod_obra_procesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->cascadeOnDelete();
            $table->foreignId('proceso_id')->constrained('prod_procesos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['obra_id', 'proceso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_obra_procesos');
    }
};
