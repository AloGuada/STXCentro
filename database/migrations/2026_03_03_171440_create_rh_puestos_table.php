<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_puestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')->constrained('departamentos');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('codigo')->nullable();
            $table->string('ubicacion')->nullable();
            $table->time('hora_entrada')->nullable();
            $table->time('hora_salida')->nullable();
            $table->unsignedBigInteger('puesto_jefe_id')->nullable();
            $table->foreign('puesto_jefe_id')->references('id')->on('rh_puestos')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_puestos');
    }
};
