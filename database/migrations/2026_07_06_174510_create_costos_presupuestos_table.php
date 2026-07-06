<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_presupuestos', function (Blueprint $table) {
            $table->id();
            $table->string('presupuestable_type');
            $table->unsignedBigInteger('presupuestable_id');
            $table->string('nombre_interno')->nullable();
            $table->string('estatus')->default('activo');
            $table->timestamps();

            $table->unique(['presupuestable_type', 'presupuestable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_presupuestos');
    }
};
