<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_factores', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->foreignId('insumo_id')->constrained('cotiz_insumos');
            $table->string('formula')->nullable();
            $table->string('descripcion')->nullable();
            $table->foreignId('categoria_tarjeta_id')->nullable()->constrained('cotiz_categorias_tarjeta')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_factores');
    }
};
