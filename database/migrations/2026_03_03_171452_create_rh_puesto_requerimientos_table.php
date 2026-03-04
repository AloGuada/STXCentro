<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_puesto_requerimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puesto_id')->constrained('rh_puestos')->cascadeOnDelete();
            $table->foreignId('requerimiento_id')->constrained('rh_requerimientos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['puesto_id', 'requerimiento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_puesto_requerimientos');
    }
};
