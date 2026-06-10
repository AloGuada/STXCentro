<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_generadora_registros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generadora_id')->constrained('cotiz_generadoras')->cascadeOnDelete();
            $table->foreignId('material_origen_id')->nullable()->constrained('cotiz_insumos')->nullOnDelete();
            $table->string('material')->nullable();
            $table->string('marca')->nullable();
            $table->decimal('ancho', 14, 6)->nullable();
            $table->decimal('largo', 14, 6)->nullable();
            $table->decimal('cantidad', 14, 6)->nullable();
            $table->decimal('cant_pzas', 14, 6)->nullable();
            // Overrides opcionales (si NULL se derivan): peso porcentual agregado y kilos totales.
            $table->decimal('peso_porcentual', 12, 6)->nullable();
            $table->decimal('kilos_totales', 14, 4)->nullable();
            $table->foreignId('merma_id')->default(1)->constrained('cotiz_mermas');
            $table->boolean('validado')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_generadora_registros');
    }
};
