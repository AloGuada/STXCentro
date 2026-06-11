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
        Schema::create('cotiz_obra_factor_override', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('cotiz_obras')->cascadeOnDelete();
            $table->foreignId('factor_id')->constrained('cotiz_factores')->cascadeOnDelete();
            // Cada campo NULL = "sin override" → cae al global del catálogo.
            $table->string('nombre')->nullable();
            $table->foreignId('insumo_id')->nullable()->constrained('cotiz_insumos')->nullOnDelete();
            $table->string('formula')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('comentario')->nullable();
            $table->timestamps();

            $table->unique(['obra_id', 'factor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotiz_obra_factor_override');
    }
};
