<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los dias de un pago extra admiten fracciones.
 *
 * Las horas extra no se trabajan en dias enteros: media jornada del sabado es
 * medio dia, y redondearla a uno le regala al grupo el doble de lo que hizo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prod_pagos_extra', function (Blueprint $table) {
            $table->decimal('dias', 8, 2)->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('prod_pagos_extra', function (Blueprint $table) {
            $table->integer('dias')->default(1)->change();
        });
    }
};
