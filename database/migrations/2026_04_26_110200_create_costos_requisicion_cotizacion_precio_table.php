<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_requisicion_cotizacion_precio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicion_detalle_id')->constrained('costos_requisicion_detalle')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->decimal('precio_unitario', 14, 2);
            $table->integer('tiempo_entrega_dias')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['requisicion_detalle_id', 'proveedor_id'],
                'costos_req_cot_precio_partida_proveedor_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_requisicion_cotizacion_precio');
    }
};
