<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignacion_firmas', function (Blueprint $table) {
            $table->id();

            // Equipo asignado
            $table->foreignId('equipo_id')
                ->constrained('equipos')
                ->cascadeOnDelete();

            // Usuario que recibe el equipo
            $table->foreignId('usuario_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Token único para generar el enlace de firma
            $table->string('token')->unique();

            // Firma del usuario
            $table->longText('firma_usuario')->nullable();

            // Firma del encargado de TI
            $table->longText('firma_encargado')->nullable();

            // Estado de la firma
            $table->string('estado')->default('pendiente');

            // Fecha en que el usuario realizó su firma
            $table->timestamp('fecha_firma_usuario')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacion_firmas');
    }
};
