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
        Schema::create('infra_compresores', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('usuario_id')->nullable()->constrained('usuarios');
            $table->boolean('compresor_1_status')->default(false);
            $table->decimal('compresor_1_presion_aire', 8, 2)->nullable();
            $table->decimal('compresor_1_tiempo_trabajo', 8, 2)->nullable();
            $table->decimal('compresor_1_tiempo_marcha', 8, 2)->nullable();
            $table->decimal('compresor_1_kwhr', 8, 2)->nullable();
            $table->boolean('compresor_2_status')->default(false);
            $table->decimal('compresor_2_presion_aire', 8, 2)->nullable();
            $table->decimal('compresor_2_tiempo_trabajo', 8, 2)->nullable();
            $table->decimal('compresor_2_tiempo_marcha', 8, 2)->nullable();
            $table->decimal('compresor_2_kwhr', 8, 2)->nullable();
            $table->boolean('compresor_3_status')->default(false);
            $table->decimal('compresor_3_presion_aire', 8, 2)->nullable();
            $table->decimal('compresor_3_tiempo_trabajo', 8, 2)->nullable();
            $table->decimal('compresor_3_tiempo_marcha', 8, 2)->nullable();
            $table->decimal('compresor_3_kwhr', 8, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infra_compresores');
    }
};
