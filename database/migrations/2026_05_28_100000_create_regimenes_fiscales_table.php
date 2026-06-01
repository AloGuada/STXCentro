<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regimenes_fiscales', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 10)->unique();
            $table->string('descripcion');
            $table->boolean('aplica_persona_fisica')->default(true);
            $table->boolean('aplica_persona_moral')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regimenes_fiscales');
    }
};
