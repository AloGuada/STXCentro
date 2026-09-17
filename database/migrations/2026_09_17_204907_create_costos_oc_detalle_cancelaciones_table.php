<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada vez que compras da por canceladas unidades de una partida de la orden.
 *
 * Nace `pendiente` con su motivo y no surte efecto hasta que el jefe de
 * compras la autoriza: mientras haya una pendiente, la orden se reporta como
 * pendiente de aprobación. Al autorizarla se escribe
 * `costos_ordenes_compra_detalle.cantidad_cancelada`, baja el total de la
 * orden y se revierte el presupuesto de esas unidades.
 *
 * Es tabla propia y no la bitácora de `HasCancelacion` porque ésa es de uno a
 * uno por documento, y aquí puede haber varias cancelaciones por orden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_oc_detalle_cancelaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orden_compra_detalle_id')->constrained('costos_ordenes_compra_detalle')->cascadeOnDelete();
            $table->decimal('cantidad', 12, 4);
            $table->text('motivo');
            $table->string('estatus')->default('pendiente');
            $table->foreignUuid('solicitado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignUuid('autorizado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('autorizado_at')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->timestamps();

            $table->index(['orden_compra_detalle_id', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_oc_detalle_cancelaciones');
    }
};
