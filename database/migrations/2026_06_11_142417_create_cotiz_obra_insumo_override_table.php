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
        Schema::create('cotiz_obra_insumo_override', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('cotiz_obras')->cascadeOnDelete();
            $table->foreignId('insumo_id')->constrained('cotiz_insumos')->cascadeOnDelete();
            // Cada campo NULL = "sin override" → cae al global del catálogo.
            $table->string('descripcion')->nullable();
            $table->string('codigo_stumis')->nullable();
            $table->foreignId('unidad_id')->nullable()->constrained('cotiz_unidades')->nullOnDelete();
            $table->decimal('precio_unitario', 14, 4)->nullable();
            $table->decimal('peso_lineal', 14, 6)->nullable();
            $table->decimal('peso_default', 14, 6)->nullable();
            $table->foreignId('centro_costo_id')->nullable()->constrained('cotiz_centros_costos')->nullOnDelete();
            $table->string('comentario')->nullable();
            $table->timestamps();

            $table->unique(['obra_id', 'insumo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotiz_obra_insumo_override');
    }
};
