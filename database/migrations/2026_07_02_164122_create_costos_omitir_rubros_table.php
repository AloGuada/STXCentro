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
        // Centros de costo (rubros) para los que un nivel (permiso) de un
        // departamento puede saltarse cuando hay presupuesto reservado. Lista
        // vacía = aplica a todos los centros de costo.
        Schema::create('costos_omitir_rubros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();
            $table->foreignId('permiso_id')->constrained('costos_permisos')->cascadeOnDelete();
            $table->foreignId('rubro_id')->constrained('costos_rubros')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['departamento_id', 'permiso_id', 'rubro_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_omitir_rubros');
    }
};
