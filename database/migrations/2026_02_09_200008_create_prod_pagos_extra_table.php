<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prod_pagos_extra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destajo_id')->constrained('prod_destajos')->cascadeOnDelete();
            $table->foreignId('tipo_id')->constrained('prod_tipos')->cascadeOnDelete();
            $table->string('descripcion')->nullable();
            $table->decimal('monto', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_pagos_extra');
    }
};
