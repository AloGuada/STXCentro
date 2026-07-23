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
        Schema::create('costos_tipos_cambio', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('moneda', 3);
            $table->string('fuente');
            $table->decimal('tasa', 14, 6);
            $table->timestamps();

            $table->unique(['fecha', 'moneda']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_tipos_cambio');
    }
};
