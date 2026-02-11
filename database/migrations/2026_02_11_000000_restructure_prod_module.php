<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old tables in reverse FK order
        Schema::dropIfExists('prod_pagos_extra');
        Schema::dropIfExists('prod_fabricados');
        Schema::dropIfExists('prod_destajos');
        Schema::dropIfExists('prod_tipos');
        Schema::dropIfExists('prod_empleados_grupo');
        Schema::dropIfExists('prod_grupos');
        Schema::dropIfExists('prod_marca_grupo');
        Schema::dropIfExists('prod_grupo_precios');
        Schema::dropIfExists('piezas');

        // 1. conceptos (replaces piezas)
        Schema::create('conceptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras');
            $table->string('marca');
            $table->string('descripcion');
            $table->decimal('peso_unitario', 10, 3);
            $table->integer('version')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 2. prod_grupos_precio
        Schema::create('prod_grupos_precio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras');
            $table->string('descripcion');
            $table->decimal('precio_kilo', 10, 4);
            $table->timestamps();
        });

        // 3. prod_grupo_precio_conceptos (pivot)
        Schema::create('prod_grupo_precio_conceptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_precio_id')->constrained('prod_grupos_precio')->cascadeOnDelete();
            $table->foreignId('concepto_id')->constrained('conceptos')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['grupo_precio_id', 'concepto_id']);
        });

        // 4. prod_grupos_trabajo
        Schema::create('prod_grupos_trabajo', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion');
            $table->integer('linea')->default(0);
            $table->integer('modulo')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 5. prod_grupo_empleados
        Schema::create('prod_grupo_empleados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_trabajo_id')->constrained('prod_grupos_trabajo')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('no_empleado')->nullable();
            $table->decimal('porcentaje', 5, 2)->default(100.00);
            $table->timestamps();
        });

        // 6. prod_registros
        Schema::create('prod_registros', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->foreignId('concepto_id')->constrained('conceptos');
            $table->foreignId('grupo_trabajo_id')->constrained('prod_grupos_trabajo');
            $table->integer('cantidad');
            $table->timestamps();
        });

        // 7. prod_cortes
        Schema::create('prod_cortes', function (Blueprint $table) {
            $table->id();
            $table->integer('semana');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('cerrado')->default(false);
            $table->datetime('fecha_cierre')->nullable();
            $table->timestamps();
        });

        // 8. prod_liquidaciones
        Schema::create('prod_liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corte_id')->constrained('prod_cortes');
            $table->foreignId('grupo_trabajo_id')->constrained('prod_grupos_trabajo');
            $table->decimal('total_kilos', 14, 3)->default(0);
            $table->decimal('total_produccion', 14, 2)->default(0);
            $table->decimal('total_extras', 14, 2)->default(0);
            $table->decimal('total_final', 14, 2)->default(0);
            $table->datetime('generado_en');
            $table->foreignUuid('generado_por')->constrained('usuarios');
            $table->timestamps();
        });

        // 9. prod_liquidacion_detalle
        Schema::create('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('prod_liquidaciones')->cascadeOnDelete();
            $table->unsignedBigInteger('concepto_id');
            $table->unsignedBigInteger('grupo_precio_id');
            $table->integer('cantidad');
            $table->decimal('kilos', 14, 3);
            $table->decimal('precio_kilo_aplicado', 10, 4);
            $table->decimal('total', 14, 2);
            $table->timestamps();
        });

        // 10. prod_extras
        Schema::create('prod_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('prod_liquidaciones')->cascadeOnDelete();
            $table->string('descripcion');
            $table->decimal('monto', 14, 2);
            $table->timestamps();
        });

        // 11. prod_liquidacion_empleados
        Schema::create('prod_liquidacion_empleados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('prod_liquidaciones')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('no_empleado')->nullable();
            $table->decimal('porcentaje', 5, 2);
            $table->decimal('monto_asignado', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_liquidacion_empleados');
        Schema::dropIfExists('prod_extras');
        Schema::dropIfExists('prod_liquidacion_detalle');
        Schema::dropIfExists('prod_liquidaciones');
        Schema::dropIfExists('prod_cortes');
        Schema::dropIfExists('prod_registros');
        Schema::dropIfExists('prod_grupo_empleados');
        Schema::dropIfExists('prod_grupos_trabajo');
        Schema::dropIfExists('prod_grupo_precio_conceptos');
        Schema::dropIfExists('prod_grupos_precio');
        Schema::dropIfExists('conceptos');
    }
};
