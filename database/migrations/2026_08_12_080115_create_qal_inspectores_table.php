<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En qué transformación trabaja cada inspector.
     *
     * No es identidad ni permiso —eso lo llevan los roles—, es la pantalla que
     * se le abre por defecto en la tablet. Va en tabla propia y no como columna
     * de `usuarios` porque es una preferencia de un solo módulo, y `usuarios`
     * la comparten todos.
     *
     * Se crea ahora, antes de que existan los formularios que la usan, para que
     * la importación del catálogo viejo no pierda el dato y no haya que volver
     * a correrla.
     */
    public function up(): void
    {
        Schema::create('qal_inspectores', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('usuario_id')->unique()->constrained('usuarios')->cascadeOnDelete();
            $table->string('fase', 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_inspectores');
    }
};
