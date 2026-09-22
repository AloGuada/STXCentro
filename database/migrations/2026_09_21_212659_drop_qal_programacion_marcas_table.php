<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El plan dejó de escribirse por marca y cantidad: va por pieza, en
     * `qal_programacion_piezas`. La tabla nunca llegó a tener datos.
     */
    public function up(): void
    {
        Schema::dropIfExists('qal_programacion_marcas');
    }

    public function down(): void
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
};
