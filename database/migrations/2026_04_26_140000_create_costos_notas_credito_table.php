<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas de credito emitidas por el proveedor sobre una factura previa.
 * Reduce el saldo pendiente de la factura. Almacena los totales clave del
 * CFDI (subtotal, monto, iva trasladado) y un snapshot JSON de impuestos
 * para auditoria. El XML/PDF se adjunta via Media polimorfica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_notas_credito', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('factura_id')->constrained('costos_facturas')->cascadeOnDelete();
            $table->string('uuid_fiscal')->nullable()->unique();
            $table->string('folio_fiscal')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('iva_trasladado', 14, 2)->default(0);
            $table->decimal('monto', 14, 2);
            $table->json('impuestos_detalle')->nullable();
            $table->string('concepto');
            $table->date('fecha_emision');
            $table->string('estatus')->default('vigente');
            $table->text('motivo_cancelacion')->nullable();
            $table->foreignUuid('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index(['factura_id', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_notas_credito');
    }
};
