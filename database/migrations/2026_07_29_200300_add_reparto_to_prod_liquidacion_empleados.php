<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot del reparto por empleado: días asistidos, salario diario vigente,
     * sueldo base y la parte del excedente que le tocó.
     *
     * Se congela igual que el detalle de piezas, para que la hoja de reparto de
     * una semana cerrada siga cuadrando aunque después cambien la configuración
     * o la categoría del trabajador.
     */
    public function up(): void
    {
        Schema::table('prod_liquidacion_empleados', function (Blueprint $table) {
            $table->unsignedInteger('dias_pagados')->default(0)->after('no_empleado');
            $table->string('categoria_nombre')->nullable()->after('dias_pagados');
            $table->unsignedInteger('categoria_valor')->default(0)->after('categoria_nombre');
            $table->decimal('salario_diario', 10, 2)->default(0)->after('categoria_valor');
            $table->decimal('sueldo_base', 14, 2)->default(0)->after('salario_diario');
            $table->decimal('monto_destajo', 14, 2)->default(0)->after('sueldo_base');
        });
    }

    public function down(): void
    {
        Schema::table('prod_liquidacion_empleados', function (Blueprint $table) {
            $table->dropColumn([
                'dias_pagados',
                'categoria_nombre',
                'categoria_valor',
                'salario_diario',
                'sueldo_base',
                'monto_destajo',
            ]);
        });
    }
};
