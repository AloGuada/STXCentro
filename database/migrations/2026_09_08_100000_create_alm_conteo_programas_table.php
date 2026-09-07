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
     * el modal —desde cuándo, qué días de la semana, cuántos días y cuántos
     * artículos por día— para que después se pueda ver por qué salieron esas
     * hojas y no otras. Las hojas cuelgan de aquí con `programa_id`.
     *
     * `articulos_sin_programar` se sella al generar: si el almacén tenía más
     * artículos de los que caben en los días elegidos, aquí queda dicho cuántos
     * se quedaron fuera, y es lo que la pantalla enseña para que se abra otro
     * programa que los cubra.
     */
    public function up(): void
    {
        Schema::create('alm_conteo_programas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_id')->constrained('alm_almacenes')->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->json('dias_semana');
            $table->unsignedSmallInteger('duracion_dias');
            $table->unsignedSmallInteger('articulos_por_dia');
            $table->unsignedInteger('articulos_programados')->default(0);
            $table->unsignedInteger('articulos_sin_programar')->default(0);
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
