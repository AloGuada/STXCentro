<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_configs', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tabla');
            $table->string('campo_estatus');
            $table->string('operador', 10)->default('=');
            $table->string('valor_estatus');
            $table->json('condiciones_extra')->nullable();
            $table->string('rol');
            $table->string('nav_href');
            $table->string('filter_href')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_configs');
    }
};
