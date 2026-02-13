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
        Schema::create('costos_afectaciones_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afectacion_id')->constrained('costos_afectaciones_presupuestales')->cascadeOnDelete();
            $table->foreignId('obra_rubro_id')->constrained('costos_obra_rubros')->cascadeOnDelete();
            $table->string('concepto');
            $table->decimal('cantidad', 14, 2);
            $table->decimal('precio_unitario', 14, 2);
            $table->decimal('monto', 14, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_afectaciones_detalle');
    }
};
