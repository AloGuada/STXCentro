<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laboratorios que firman los informes de ensayos no destructivos.
     *
     * Tabla propia y no `proveedores`: aquí un laboratorio interesa por sus
     * siglas —que es como se le nombra en el informe— y por quién firma, no por
     * su RFC ni sus condiciones de pago. Si además se le paga, vivirá también
     * como proveedor, y son dos hechos distintos sobre la misma empresa.
     */
    public function up(): void
    {
        Schema::create('qal_laboratorios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('siglas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_laboratorios');
    }
};
