<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los días pagados dejan de ser enteros: el séptimo día se prorratea entre
     * los seis de trabajo (7/6 por día cubierto), así que una semana completa
     * son 7.0000 y cinco días 5.8333.
     */
    public function up(): void
    {
        Schema::table('prod_liquidacion_empleados', function (Blueprint $table) {
            $table->decimal('dias_pagados', 6, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('prod_liquidacion_empleados', function (Blueprint $table) {
            $table->unsignedInteger('dias_pagados')->default(0)->change();
        });
    }
};
