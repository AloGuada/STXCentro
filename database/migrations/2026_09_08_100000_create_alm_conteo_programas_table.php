<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un programa de inventario: la decisión de contar un almacén por partes.
     *
     * Es la cabecera de un lote de hojas de conteo. Guarda lo que se eligió en
     * el modal —desde cuándo, qué días de la semana y cuántos artículos por
     * día— para que después se pueda ver por qué salieron esas hojas y no
     * otras. Las hojas cuelgan de aquí con `programa_id`.
     *
     * `fecha_fin` y `articulos_programados` no se capturan: los calcula el
     * sistema al generar. El programa siempre cubre el almacén completo —las
     * hojas que hagan falta, repartidas sobre los días elegidos— y la fecha en
     * que cae la última es la fecha en que se termina. Se sellan porque el
     * universo se congela en ese momento: lo que se dé de alta después espera
     * al siguiente programa.
     */
    public function up(): void
    {
        Schema::create('alm_conteo_programas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_id')->constrained('alm_almacenes')->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->json('dias_semana');
            $table->unsignedSmallInteger('articulos_por_dia');
            $table->unsignedInteger('articulos_programados')->default(0);
            $table->foreignUuid('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index(['almacen_id', 'fecha_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_conteo_programas');
    }
};
