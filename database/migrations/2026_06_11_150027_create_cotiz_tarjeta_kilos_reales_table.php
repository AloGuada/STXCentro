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
        Schema::create('cotiz_tarjeta_kilos_reales', function (Blueprint $table) {
            // Celdas de la matriz: una fila por (tarjeta, categoría, estructura).
            $table->id();
            $table->foreignId('tarjeta_id')->constrained('cotiz_tarjetas')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('cotiz_kilos_reales_categorias')->cascadeOnDelete();
            $table->foreignId('estructura_id')->constrained('cotiz_tarjeta_estructuras')->cascadeOnDelete();
            $table->decimal('kilos', 16, 4)->default(0);
            $table->timestamps();

            $table->unique(['tarjeta_id', 'categoria_id', 'estructura_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotiz_tarjeta_kilos_reales');
    }
};
