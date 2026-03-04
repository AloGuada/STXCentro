<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_permisos_ausencia', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->nullable();
            $table->string('numero_empleado')->nullable();
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('departamento')->nullable();
            $table->string('gerente')->nullable();
            $table->string('tipo')->nullable();
            $table->string('modalidad')->nullable();
            $table->string('condicion')->nullable();
            $table->text('razon')->nullable();
            $table->date('fecha_permiso')->nullable();
            $table->date('fecha_elaboracion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_permisos_ausencia');
    }
};
