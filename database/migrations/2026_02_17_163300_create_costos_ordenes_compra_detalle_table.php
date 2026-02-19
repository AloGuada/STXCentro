<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('costos_ordenes_compra')->cascadeOnDelete();
            $table->foreignId('obra_rubro_id')->constrained('costos_obra_rubros');
            $table->string('descripcion');
            $table->string('unidad', 20)->default('pza');
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario', 14, 2);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('cantidad_recibida', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_ordenes_compra_detalle');
    }
};
