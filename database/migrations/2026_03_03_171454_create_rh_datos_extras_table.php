<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_datos_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('rh_personas')->cascadeOnDelete();
            $table->string('estado_civil')->nullable();
            $table->integer('hijos')->nullable();
            $table->string('localidad')->nullable();
            $table->string('domicilio')->nullable();
            $table->string('cp')->nullable();
            $table->string('nombre_padre')->nullable();
            $table->string('nombre_madre')->nullable();
            $table->string('cuenta_banco')->nullable();
            $table->string('c_infonavit')->nullable();
            $table->string('c_fonacot')->nullable();
            $table->string('imss')->nullable();
            $table->string('curp')->nullable();
            $table->string('rfc')->nullable();
            $table->string('banco_op')->nullable();
            $table->text('texto_cv')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_datos_extras');
    }
};
