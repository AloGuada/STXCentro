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
        Schema::create('cotiz_tarjeta_insumo_precio', function (Blueprint $table) {
            // M040: override de P.U. por tarjeta — el nivel más específico de la cadena
            // de precios (tarjeta > obra > global).
            $table->id();
            $table->foreignId('tarjeta_id')->constrained('cotiz_tarjetas')->cascadeOnDelete();
            $table->foreignId('insumo_id')->constrained('cotiz_insumos')->cascadeOnDelete();
            $table->decimal('precio_unitario', 14, 4);
            $table->timestamps();

            $table->unique(['tarjeta_id', 'insumo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotiz_tarjeta_insumo_precio');
    }
};
