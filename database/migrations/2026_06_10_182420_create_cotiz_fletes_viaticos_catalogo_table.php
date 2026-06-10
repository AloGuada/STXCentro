<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_fletes_viaticos_catalogo', function (Blueprint $table) {
            $table->id();
            $table->string('grupo');
            $table->integer('orden')->default(0);
            $table->string('concepto');
            $table->string('unidad')->nullable();
            $table->decimal('p_unit_default', 14, 4)->default(0);
            $table->string('notas')->nullable();
            $table->string('clave')->nullable();
            $table->string('formula_cantidad')->nullable();
            $table->string('formula_p_unit')->nullable();
            $table->timestamps();

            $table->index('grupo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_fletes_viaticos_catalogo');
    }
};
