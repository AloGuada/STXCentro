<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_cuadrillas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->foreignId('centro_costo_id')->constrained('cotiz_centros_costos');
            $table->foreignId('insumo_id')->nullable()->constrained('cotiz_insumos')->nullOnDelete();
            $table->decimal('rendimiento', 14, 6)->nullable();
            $table->string('formula_costo')->nullable();
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_cuadrillas');
    }
};
