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
        Schema::create('costos_complementos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('factura_id')->constrained('costos_facturas')->cascadeOnDelete();
            $table->foreignId('pago_id')->unique()->constrained('costos_pagos')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->decimal('monto_pago', 14, 2);
            $table->date('fecha_pago');
            $table->date('fecha_generacion');
            $table->date('fecha_limite');
            $table->string('estatus')->default('pendiente');
            $table->string('complemento_uuid')->nullable()->unique();
            $table->timestamp('recibido_at')->nullable();
            $table->timestamps();

            $table->index(['proveedor_id', 'estatus']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_complementos_pago');
    }
};
