<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supervisores de pintura. Se eligen en 3ª transformación, en el lugar que
     * en 2ª ocupa el responsable de módulo.
     */
    public function up(): void
    {
        Schema::create('qal_supervisores_pintura', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_supervisores_pintura');
    }
};
