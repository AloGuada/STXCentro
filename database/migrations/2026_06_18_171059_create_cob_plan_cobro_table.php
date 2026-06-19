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
        Schema::create('cob_plan_cobro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->unsignedInteger('orden'); // 1..N (estimación planeada)
            $table->unsignedInteger('dias'); // duración planeada del cobro
            $table->date('fecha_inicio_plan');
            $table->date('fecha_fin_plan');
            $table->timestamps();

            $table->unique(['proyecto_id', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cob_plan_cobro');
    }
};
