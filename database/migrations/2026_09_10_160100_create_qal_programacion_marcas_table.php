<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las marcas del plan con su cantidad, y las bajas con su motivo, en la
     * misma lista con una bandera.
     *
     * Son filas y no el texto pegado (RN-25): guardarlas como
     * `"PJ-CM1-5 x3, PJ-CM1-6"` impedía preguntar en qué semanas se programó
     * una marca. `concepto_id` la amarra al catálogo vigente cuando la marca es
     * única en él; `marca` conserva lo que se escribió.
     */
    public function up(): void
    {
        Schema::create('qal_programacion_marcas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programacion_id')->constrained('qal_programaciones')->cascadeOnDelete();
            $table->foreignId('concepto_id')->nullable()->constrained('conceptos')->nullOnDelete();
            $table->string('marca');
            $table->unsignedSmallInteger('cantidad')->default(1);
            $table->boolean('es_baja')->default(false);
            $table->string('motivo_baja')->nullable();
            $table->timestamps();

            $table->unique(['programacion_id', 'marca', 'es_baja']);
            $table->index('marca');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_programacion_marcas');
    }
};
