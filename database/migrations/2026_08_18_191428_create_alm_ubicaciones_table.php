<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lugares físicos dentro de un almacén. El almacén dice en qué bodega está
     * el material; esto dice en qué anaquel, que es lo que se necesita para ir
     * por él y para recorrer una zona contando.
     *
     * Cuelgan unos de otros —un nivel vive dentro de un rack y el rack dentro de
     * un pasillo— en vez de ser tres columnas, porque cada almacén ordena su
     * espacio distinto: el contenedor de una obra no tiene racks ni niveles.
     *
     * `activa` en vez de borrado: un pasillo que se vacía sigue nombrado en los
     * movimientos viejos del kardex.
     */
    public function up(): void
    {
        Schema::create('alm_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_id')->constrained('alm_almacenes')->cascadeOnDelete();
            $table->foreignId('padre_id')->nullable()->constrained('alm_ubicaciones')->cascadeOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre');
            $table->string('tipo', 20);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['almacen_id', 'codigo']);
            $table->index(['almacen_id', 'padre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_ubicaciones');
    }
};
