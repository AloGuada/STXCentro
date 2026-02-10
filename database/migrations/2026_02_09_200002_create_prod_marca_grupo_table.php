<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prod_marca_grupo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pieza_id')->constrained('piezas')->cascadeOnDelete();
            $table->foreignId('grupo_precio_id')->constrained('prod_grupo_precios')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['pieza_id', 'grupo_precio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_marca_grupo');
    }
};
