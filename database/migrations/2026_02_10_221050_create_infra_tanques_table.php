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
        Schema::create('infra_tanques', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('usuario_id')->nullable()->constrained('usuarios');
            $table->decimal('pa_sistema_oxigeno', 8, 2)->nullable();
            $table->decimal('presion_sistema_oxigeno', 8, 2)->nullable();
            $table->decimal('presion_tanque_oxigeno', 8, 2)->nullable();
            $table->decimal('lt_tanque_oxigeno', 8, 2)->nullable();
            $table->decimal('kg_tanque_oxigeno', 8, 2)->nullable();
            $table->decimal('pa_sistema_argon', 8, 2)->nullable();
            $table->decimal('presion_sistema_argon', 8, 2)->nullable();
            $table->decimal('presion_tanque_argon', 8, 2)->nullable();
            $table->decimal('lt_tanque_argon', 8, 2)->nullable();
            $table->decimal('kg_tanque_argon', 8, 2)->nullable();
            $table->decimal('pa_sistema_co2', 8, 2)->nullable();
            $table->decimal('presion_sistema_co2', 8, 2)->nullable();
            $table->decimal('presion_tanque_co2', 8, 2)->nullable();
            $table->decimal('lt_tanque_co2', 8, 2)->nullable();
            $table->decimal('kg_tanque_co2', 8, 2)->nullable();
            $table->decimal('pa_sistema_lp', 8, 2)->nullable();
            $table->decimal('presion_sistema_lp', 8, 2)->nullable();
            $table->decimal('presion_tanque_lp', 8, 2)->nullable();
            $table->decimal('numero_tanque_lp', 8, 2)->nullable();
            $table->decimal('lt_tanque_lp', 8, 2)->nullable();
            $table->decimal('kg_tanque_lp', 8, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infra_tanques');
    }
};
