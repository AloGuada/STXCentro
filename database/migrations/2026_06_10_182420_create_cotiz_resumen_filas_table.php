<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_resumen_filas', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion');
            $table->string('bloque');
            $table->string('tipo_formula');
            $table->decimal('coef_default', 14, 6)->nullable();
            $table->string('referencia_extra')->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('bloqueada')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_resumen_filas');
    }
};
