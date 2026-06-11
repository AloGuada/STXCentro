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
        Schema::create('cotiz_tarjeta_generadoras', function (Blueprint $table) {
            // Vínculo N:N: una tarjeta consolida los registros de varias generadoras.
            $table->id();
            $table->foreignId('tarjeta_id')->constrained('cotiz_tarjetas')->cascadeOnDelete();
            $table->foreignId('generadora_id')->constrained('cotiz_generadoras')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tarjeta_id', 'generadora_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotiz_tarjeta_generadoras');
    }
};
