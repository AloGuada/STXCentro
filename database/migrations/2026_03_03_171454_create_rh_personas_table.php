<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_personas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('cv_ruta')->nullable();
            $table->enum('cv_estado', ['pendiente', 'procesando', 'procesado', 'error'])->nullable();
            $table->timestamp('cv_procesado_at')->nullable();
            $table->text('error_procesamiento')->nullable();
            $table->integer('reintentos')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_personas');
    }
};
