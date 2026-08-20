<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La rejilla del informe: un renglón por punto de examen.
     *
     * **Un renglón es un spot, no una junta.** `J-18-1-2` es el segundo punto
     * examinado de la junta `18-1`; contar juntas en lugar de spots subestima el
     * volumen ensayado y desvía el porcentaje de rechazo, que es el número que
     * mira el cliente. Por eso `spot` se persiste en su columna en vez de
     * quedarse dentro del texto de la referencia.
     *
     * `marca` guarda **el texto tal como lo escribió el laboratorio** y
     * `qal_pieza_id` queda nulo mientras esa marca no exista como pieza. El
     * laboratorio entrega antes de que Calidad dé de alta las piezas, así que
     * exigir la pieza para poder capturar el informe sería no poder capturarlo.
     * El enlace se resuelve después, y el texto no se pierde ni cuando se
     * resuelve: es lo que dice el informe firmado.
     *
     * No lleva unique de (informe, junta, spot): un mismo punto puede aparecer
     * dos veces en el informe cuando se reexamina después de reparar, y eso es
     * un dato del laboratorio, no un error de captura.
     */
    public function up(): void
    {
        Schema::create('qal_pnd_juntas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qal_pnd_reporte_id')->constrained('qal_pnd_reportes')->cascadeOnDelete();
            $table->foreignId('qal_pieza_id')->nullable()->constrained('qal_piezas')->nullOnDelete();
            $table->string('marca');
            $table->string('junta');
            $table->string('modulo')->nullable();
            $table->unsignedSmallInteger('spot')->default(1);
            $table->string('resultado');
            $table->string('discontinuidad')->nullable();
            $table->decimal('longitud_discontinuidad', 8, 2)->nullable();
            $table->decimal('espesor', 8, 2)->nullable();
            $table->foreignId('soldador_id')->nullable()->constrained('qal_soldadores')->nullOnDelete();
            $table->timestamps();

            $table->index(['qal_pnd_reporte_id', 'resultado']);
            $table->index('marca');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_pnd_juntas');
    }
};
