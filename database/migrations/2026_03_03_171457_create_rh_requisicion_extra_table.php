<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_requisicion_extra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicion_id')->constrained('rh_requisiciones')->cascadeOnDelete();
            $table->decimal('salario_mensual', 12, 2)->nullable();
            $table->decimal('salario_diario', 12, 2)->nullable();
            $table->string('periodicidad_pago')->nullable();
            $table->text('prestaciones')->nullable();
            $table->text('bonos')->nullable();
            $table->text('horario')->nullable();
            $table->string('tipo_jornada')->nullable();
            $table->text('beneficios_adicionales')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_requisicion_extra');
    }
};
