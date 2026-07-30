<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asistencia diaria por empleado dentro de un destajo. Es obligatoria:
     * parte del pago del trabajador va a salario base y depende de los días
     * efectivamente trabajados.
     */
    public function up(): void
    {
        Schema::create('prod_asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destajo_id')->constrained('prod_destajos')->cascadeOnDelete();
            $table->foreignId('grupo_empleado_id')->constrained('prod_grupo_empleados')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('estado')->default('asistencia');
            $table->timestamps();

            $table->unique(['grupo_empleado_id', 'fecha']);
            $table->index(['destajo_id', 'grupo_empleado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_asistencias');
    }
};
