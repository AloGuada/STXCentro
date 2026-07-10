<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bancos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            // Longitud del número de cuenta cuando este banco es el pagador
            // (transferencias mismo banco). Null cuando solo opera por CLABE.
            $table->unsignedTinyInteger('digitos_cuenta')->nullable();
            // Banco pagador de la empresa. Solo uno puede estar activo a la vez;
            // el invariante se garantiza en el modelo/controlador.
            $table->boolean('es_pagador')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bancos');
    }
};
