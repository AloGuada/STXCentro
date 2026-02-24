<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cob_estimaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->cascadeOnDelete();
            $table->unsignedInteger('numero_estimacion');
            $table->string('folio')->nullable();
            $table->string('tipo')->nullable();
            $table->date('fecha_emision')->nullable();
            $table->date('inicio')->nullable();
            $table->date('fin')->nullable();
            $table->decimal('monto_estimado', 15, 2)->default(0);
            $table->decimal('monto_total', 15, 2)->default(0);
            $table->decimal('monto_pagado', 15, 2)->default(0);
            $table->string('moneda', 3)->default('MXN');
            $table->string('estado')->default('pendiente');
            $table->timestamp('fecha_ultimo_cambio_estado')->nullable();
            $table->text('comentarios')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_estimaciones');
    }
};
