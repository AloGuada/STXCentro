<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los pasos en que se desmenuza un proceso dentro de un grupo de precios:
     * soldadura puede ser armado, punteado y soldado final, cada uno con su
     * precio fijo por pieza.
     *
     * Cuelgan del grupo y no del proceso a propósito: la lista de pasos cambia
     * según el modelo que se esté fabricando, y el grupo es justamente el que
     * junta a las marcas que se trabajan igual.
     *
     * `precio` es un importe por pieza, no por kilo: aquí el peso no explica lo
     * que cuesta el trabajo.
     */
    public function up(): void
    {
        Schema::create('prod_grupo_precio_subprocesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_precio_id')->constrained('prod_grupos_precio')->cascadeOnDelete();
            $table->foreignId('proceso_id')->constrained('prod_procesos')->cascadeOnDelete();
            $table->string('nombre');
            $table->unsignedInteger('orden')->default(0);
            $table->decimal('precio', 10, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['grupo_precio_id', 'proceso_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_grupo_precio_subprocesos');
    }
};
