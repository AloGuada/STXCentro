<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devoluciones a proveedor sobre partidas ya recibidas. Cada devolucion
 * apunta a un entrega_detalle (la recepcion fisica). Reduce la cantidad
 * neta recibida via accessor en EntregaDetalle. El ajuste contable
 * (nota de credito) se maneja por separado en Fase 12.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_devoluciones', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('entrega_detalle_id')->constrained('costos_entrega_detalle')->cascadeOnDelete();
            $table->decimal('cantidad', 14, 2);
            $table->text('motivo');
            $table->date('fecha');
            $table->string('estatus')->default('vigente');
            $table->text('motivo_cancelacion')->nullable();
            $table->foreignUuid('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index(['entrega_detalle_id', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_devoluciones');
    }
};
