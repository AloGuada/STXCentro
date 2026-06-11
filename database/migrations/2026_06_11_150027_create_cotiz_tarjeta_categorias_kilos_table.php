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
        Schema::create('cotiz_tarjeta_categorias_kilos', function (Blueprint $table) {
            // M033/M034: qué categorías de kilos reales usa esta tarjeta en su análisis.
            // porcentual NULL = fila fija (kilos editables por celda); con valor (ej. 0.22) =
            // fila porcentual: kilos = porcentual × Σ celdas fijas.
            $table->id();
            $table->foreignId('tarjeta_id')->constrained('cotiz_tarjetas')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('cotiz_kilos_reales_categorias')->cascadeOnDelete();
            $table->integer('orden')->default(0);
            $table->decimal('porcentual', 12, 6)->nullable();
            $table->timestamps();

            $table->unique(['tarjeta_id', 'categoria_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotiz_tarjeta_categorias_kilos');
    }
};
