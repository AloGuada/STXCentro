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
        Schema::create('infra_transformadores', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('usuario_id')->nullable()->constrained('usuarios');
            $table->decimal('linea_A', 8, 2)->nullable();
            $table->decimal('linea_A_max', 8, 2)->nullable();
            $table->dateTime('date_A')->nullable();
            $table->decimal('linea_B', 8, 2)->nullable();
            $table->decimal('linea_B_max', 8, 2)->nullable();
            $table->dateTime('date_B')->nullable();
            $table->decimal('linea_C', 8, 2)->nullable();
            $table->decimal('linea_C_max', 8, 2)->nullable();
            $table->dateTime('date_C')->nullable();
            $table->decimal('total_1', 8, 2)->nullable();
            $table->decimal('total_5', 8, 2)->nullable();
            $table->decimal('lectura_5y5', 8, 2)->nullable();
            $table->decimal('lectura_301', 8, 2)->nullable();
            $table->decimal('lectura_302', 8, 2)->nullable();
            $table->decimal('lectura_303', 8, 2)->nullable();
            $table->decimal('lectura_310', 8, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infra_transformadores');
    }
};
