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
        Schema::create('costos_afectaciones_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afectacion_id')->constrained('costos_afectaciones_presupuestales')->cascadeOnDelete();
            $table->string('estatus_anterior');
            $table->string('estatus_nuevo');
            $table->timestamp('fecha');
            $table->foreignUuid('usuario_id')->nullable()->constrained('usuarios');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_afectaciones_historial');
    }
};
