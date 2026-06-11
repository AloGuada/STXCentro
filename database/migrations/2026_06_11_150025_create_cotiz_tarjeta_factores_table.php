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
        Schema::create('cotiz_tarjeta_factores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarjeta_id')->constrained('cotiz_tarjetas')->cascadeOnDelete();
            $table->foreignId('factor_id')->constrained('cotiz_factores')->cascadeOnDelete();
            $table->decimal('cantidad_manual', 16, 6)->nullable();
            $table->decimal('importe', 16, 4)->nullable();
            $table->boolean('validado')->default(false);
            // M031: override de fórmula por-tarjeta (gana sobre obra y global).
            $table->string('formula_override')->nullable();
            $table->timestamps();

            $table->unique(['tarjeta_id', 'factor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotiz_tarjeta_factores');
    }
};
