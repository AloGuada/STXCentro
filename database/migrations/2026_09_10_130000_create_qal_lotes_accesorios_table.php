<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un lote de accesorios: cientos de unidades iguales de una marca, que
     * llegan en entregas y se liberan por muestreo, no pieza por pieza.
     *
     * La marca se escribe como texto y es única por obra: es lo que teclea el
     * inspector y lo que identifica al lote entre entregas. Si existe en el
     * catálogo vigente de Producción se amarra a ella, pero el lote no depende
     * de que exista.
     */
    public function up(): void
    {
        Schema::create('qal_lotes_accesorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->restrictOnDelete();
            $table->foreignId('concepto_id')->nullable()->constrained('conceptos')->nullOnDelete();
            $table->string('marca', 80);
            $table->string('descripcion')->nullable();
            $table->unsignedInteger('total_unidades');
            $table->decimal('kg_unitario', 10, 3)->nullable();
            $table->unsignedSmallInteger('elementos_unitarios')->nullable();
            $table->foreignUuid('capturista_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->unique(['obra_id', 'marca']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_lotes_accesorios');
    }
};
