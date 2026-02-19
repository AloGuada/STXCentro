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
        Schema::create('costos_abonos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('costos_pagos')->cascadeOnDelete();
            $table->unsignedInteger('numero_parcialidad');
            $table->decimal('monto_parcialidad', 14, 2);
            $table->decimal('monto_abonado', 14, 2)->default(0);
            $table->date('fecha_programada');
            $table->date('fecha_pago_real')->nullable();
            $table->string('ruta_comprobante')->nullable();
            $table->string('estatus')->default('pendiente'); // pendiente, pagado
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_abonos');
    }
};
