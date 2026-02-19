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
        Schema::create('costos_pagos', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->morphs('pagable');
            $table->decimal('monto_pago', 14, 2);
            $table->string('moneda')->default('mxn');
            $table->decimal('tipo_cambio', 10, 4)->default(1);
            $table->string('tipo_pago'); // contado, credito
            $table->date('fecha_pago_programada')->nullable();
            $table->date('fecha_pago_maxima')->nullable();
            $table->date('fecha_pago_realizada')->nullable();
            $table->string('referencia_pago')->nullable();
            $table->string('ruta_comprobante')->nullable();
            $table->string('estatus')->default('pendiente'); // pendiente, parcial, pagado
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_pagos');
    }
};
