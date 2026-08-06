<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El almacén es virtual: vive dentro de un edificio u obra (T4, MBP) y se
     * identifica por su clave corta (AG, FAK, FAD, A, E). Un almacén sin obra es
     * central/corporativo, que es como se distingue "insumos" de "montaje".
     *
     * La clave es única dentro de la obra, no global: cada obra puede tener su
     * propio AG sin chocar con el de la de al lado.
     */
    public function up(): void
    {
        Schema::create('alm_almacenes', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 20);
            $table->string('nombre');
            $table->foreignId('obra_id')->nullable()->constrained('obras')->nullOnDelete();
            $table->string('tipo', 20)->default('insumos');
            $table->foreignUuid('responsable_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['obra_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_almacenes');
    }
};
