<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_entrega_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_id')->constrained('costos_entregas')->cascadeOnDelete();
            $table->foreignId('orden_compra_detalle_id')->constrained('costos_ordenes_compra_detalle');
            $table->decimal('cantidad_recibida', 12, 2);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_entrega_detalle');
    }
};
