<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada marca del modelo, con los archivos que dejó la conversión (`archivo`
     * es el nombre del .glb y del .json) y su marca de Producción cuando existe
     * en el catálogo vigente.
     *
     * Las piezas de la marca no se guardan aquí: las lee el visor de su .json.
     */
    public function up(): void
    {
        Schema::create('qal_modelo_marcas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modelo_id')->constrained('qal_modelos')->cascadeOnDelete();
            $table->string('marca', 80);
            $table->string('archivo', 120);
            $table->foreignId('concepto_id')->nullable()->constrained('conceptos')->nullOnDelete();
            $table->string('nombre')->nullable();
            $table->unsignedInteger('piezas')->default(0);
            $table->decimal('peso_kg', 12, 2)->default(0);
            $table->unsignedInteger('ensambles')->default(0);
            $table->unsignedInteger('soldaduras')->default(0);
            $table->json('bbox_mm')->nullable();
            $table->timestamps();

            $table->unique(['modelo_id', 'marca']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_modelo_marcas');
    }
};
