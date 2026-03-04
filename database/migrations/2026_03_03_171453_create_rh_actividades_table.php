<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puesto_id')->constrained('rh_puestos')->cascadeOnDelete();
            $table->text('descripcion');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_actividades');
    }
};
