<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El plan de la semana, pieza por pieza: qué QR se va a hacer, qué grupo
     * de trabajo lo hace y en qué módulo. El módulo se escribe («1.2»): es
     * como lo nombra planta, no un catálogo.
     *
     * La marca, el lote, el QR y el QS van congelados en el renglón: el
     * catálogo se versiona y el mismo QR vive en varias versiones, así que el
     * cruce con las inspecciones es por el QR escrito y no por `pieza_id`.
     */
    public function up(): void
    {
        Schema::create('qal_programacion_piezas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programacion_id')->constrained('qal_programaciones')->cascadeOnDelete();
            $table->foreignId('pieza_id')->nullable()->constrained('prod_piezas')->nullOnDelete();
            $table->foreignId('concepto_id')->nullable()->constrained('conceptos')->nullOnDelete();
            $table->string('marca');
            $table->string('lote')->nullable();
            $table->string('qr', 100);
            $table->string('qs')->nullable();
            $table->foreignId('grupo_trabajo_id')->constrained('prod_grupos_trabajo');
            $table->string('modulo', 50)->nullable();
            $table->timestamps();

            $table->unique(['programacion_id', 'qr']);
            $table->index('qr');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_programacion_piezas');
    }
};
