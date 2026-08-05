<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El grupo de precios deja de tener un solo $/kg: ahora cobra una tarifa por
     * proceso, porque soldar y pintar la misma pieza no valen lo mismo.
     *
     * La columna `precio_kilo` del grupo se elimina para que no queden dos
     * fuentes de verdad; el precio vive sólo aquí.
     */
    public function up(): void
    {
        Schema::create('prod_grupo_precio_procesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_precio_id')->constrained('prod_grupos_precio')->cascadeOnDelete();
            $table->foreignId('proceso_id')->constrained('prod_procesos')->cascadeOnDelete();
            $table->decimal('precio_kilo', 10, 4)->default(0);
            $table->timestamps();

            $table->unique(['grupo_precio_id', 'proceso_id']);
        });

        Schema::table('prod_grupos_precio', function (Blueprint $table) {
            $table->dropColumn('precio_kilo');
        });
    }

    public function down(): void
    {
        Schema::table('prod_grupos_precio', function (Blueprint $table) {
            $table->decimal('precio_kilo', 10, 4)->default(0);
        });

        Schema::dropIfExists('prod_grupo_precio_procesos');
    }
};
