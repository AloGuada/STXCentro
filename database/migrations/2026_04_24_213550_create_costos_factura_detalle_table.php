<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_factura_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('costos_facturas')->cascadeOnDelete();
            $table->foreignId('orden_compra_detalle_id')->constrained('costos_ordenes_compra_detalle');
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario', 14, 2);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_factura_detalle');
    }
};
