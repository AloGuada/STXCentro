<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las juntas del mapeo de soldadura, junta por junta, de una inspección de
     * 2ª en soldado.
     *
     * En la aplicación anterior una junta era una fila más de `registros`, la
     * misma tabla que las piezas, y cada consulta de piezas tenía que acordarse
     * de filtrarlas. Aquí es tabla propia.
     *
     * Sin modelo 3D la junta es sólo su identificador. Con modelo, además apunta
     * al cordón detectado (`cordon_id`); la llave se constriñe cuando exista la
     * tabla de cordones.
     *
     * `intento` y `resultado` los calcula el servidor: el intento cuenta cuántas
     * veces se ha revisado esa junta de esa pieza, y el resultado sale de los
     * puntos.
     */
    public function up(): void
    {
        Schema::create('qal_juntas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspeccion_id')->constrained('qal_inspecciones')->cascadeOnDelete();
            $table->unsignedBigInteger('cordon_id')->nullable();
            $table->string('identificador', 40);
            $table->string('tipo', 10);
            $table->foreignId('soldador_id')->nullable()->constrained('qal_soldadores')->nullOnDelete();
            $table->decimal('espesor_requerido_mm', 8, 2)->nullable();
            $table->decimal('espesor_medido_mm', 8, 2)->nullable();
            $table->boolean('espesor_cumple')->nullable();
            // Un empate une dos tramos del mismo miembro: el reporte de
            // soldaduras lo separa de las demás juntas.
            $table->boolean('es_empate')->default(false);
            $table->unsignedSmallInteger('intento')->default(1);
            $table->string('resultado', 12);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['inspeccion_id', 'identificador']);
            $table->index('cordon_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_juntas');
    }
};
