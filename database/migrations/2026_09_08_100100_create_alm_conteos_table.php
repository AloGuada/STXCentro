<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La hoja de conteo: qué se cuenta, en qué almacén y qué día. Folio `CIC`.
     *
     * Una hoja no mueve saldo. Cuando se cierre con diferencias generará un
     * ajuste con motivo `conteo_fisico`, y ése es el que toca el kardex;
     * `ajuste_id` es la liga hacia atrás. Hoy la hoja nace del programa
     * (`origen = programado`); el conteo suelto, que se levanta a mano cuando
     * hay sospecha de faltante, usa la misma tabla con `programa_id` nulo.
     */
    public function up(): void
    {
        Schema::create('alm_conteos', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->foreignId('programa_id')->nullable()->constrained('alm_conteo_programas')->nullOnDelete();
            $table->foreignId('almacen_id')->constrained('alm_almacenes')->restrictOnDelete();
            $table->string('origen', 20);
            $table->date('fecha_programada');
            $table->string('estatus', 20);
            $table->foreignUuid('responsable_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->date('fecha_cierre')->nullable();
            $table->foreignId('ajuste_id')->nullable()->constrained('alm_ajustes')->nullOnDelete();
            $table->text('observaciones')->nullable();
            // La hoja firmada, escaneada, si la subieron al cerrar. Opcional:
            // el acta con valor contable es el ajuste; esto es el papel que la
            // respalda.
            $table->string('firmado_path')->nullable();
            $table->foreignUuid('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index(['almacen_id', 'fecha_programada']);
            $table->index('estatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_conteos');
    }
};
