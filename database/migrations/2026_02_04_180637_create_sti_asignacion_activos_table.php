<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sti_asignacion_activos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained('sti_equipos')->cascadeOnDelete();
            $table->string('no_empleado');
            $table->string('empleado');
            $table->text('firma_empleado')->nullable();
            $table->string('no_ti');
            $table->string('nombre_ti');
            $table->text('firma_ti')->nullable();
            $table->date('fecha_inicial');
            $table->date('fecha_termino')->nullable();
            $table->string('estado')->default('activo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sti_asignacion_activos');
    }
};
