<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cob_anticipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->cascadeOnDelete();
            $table->string('folio')->nullable();
            $table->date('fecha_emision')->nullable();
            $table->decimal('monto', 15, 2)->default(0);
            $table->string('moneda', 3)->default('MXN');
            $table->string('estado')->default('pendiente');
            $table->text('comentarios')->nullable();
            $table->date('fecha_pagado')->nullable();
            $table->string('comprobante')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_anticipos');
    }
};
