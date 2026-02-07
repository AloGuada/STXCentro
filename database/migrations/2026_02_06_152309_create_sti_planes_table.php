<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sti_planes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('sti_equipos')->cascadeOnDelete();
            $table->string('descripcion');
            $table->unsignedInteger('periodicidad')->comment('días entre mantenimientos');
            $table->date('fecha_inicial');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sti_planes');
    }
};
