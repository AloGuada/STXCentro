<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_obra_fletes_viaticos', function (Blueprint $table) {
            // Tabla plana por obra: cada renglón es un item de uno de los 9 grupos del Excel.
            // importe = cantidad × p_unit (derivado en PHP). M043: fórmulas por concepto cuyo
            // resultado se PERSISTE en cantidad/p_unit (patrón cache).
            $table->id();
            $table->foreignId('obra_id')->constrained('cotiz_obras')->cascadeOnDelete();
            $table->string('grupo');
            $table->unsignedInteger('orden')->default(0);
            $table->string('concepto');
            $table->string('unidad')->nullable();
            $table->decimal('cantidad', 16, 4)->default(0);
            $table->decimal('p_unit', 16, 4)->default(0);
            $table->string('notas')->nullable();
            $table->string('clave')->nullable();
            $table->string('formula_cantidad')->nullable();
            $table->string('formula_p_unit')->nullable();
            $table->timestamps();

            $table->index(['obra_id', 'grupo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_obra_fletes_viaticos');
    }
};
