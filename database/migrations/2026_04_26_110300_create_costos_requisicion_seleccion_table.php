<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_requisicion_seleccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicion_detalle_id')->constrained('costos_requisicion_detalle')->cascadeOnDelete();
            $table->foreignId('cotizacion_precio_id')->constrained('costos_requisicion_cotizacion_precio')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->decimal('cantidad', 14, 2);
            // Se llena en sub-fase 10.4 al generar las OC
            $table->foreignId('obra_rubro_id')->nullable()->constrained('costos_obra_rubros')->nullOnDelete();
            $table->foreignId('orden_compra_detalle_id')->nullable()->constrained('costos_ordenes_compra_detalle')->nullOnDelete();
            $table->timestamps();

            $table->index(['requisicion_detalle_id', 'proveedor_id'], 'costos_req_seleccion_partida_proveedor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_requisicion_seleccion');
    }
};
