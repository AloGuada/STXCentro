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
            $table->string('descripcion');
            $table->foreignId('tipo_id')->constrained('prod_tipos');
            $table->foreignId('corte_id')->constrained('prod_cortes');
            $table->foreignId('grupo_trabajo_id')->constrained('prod_grupos_trabajo');
            $table->decimal('precio', 14, 2);
            $table->integer('dias')->default(1);
            $table->integer('personas')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_pagos_extra');
    }
};
