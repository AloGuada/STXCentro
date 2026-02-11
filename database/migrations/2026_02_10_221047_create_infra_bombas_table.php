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
        Schema::create('infra_bombas', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('usuario_id')->nullable()->constrained('usuarios');
            $table->boolean('bomba_posos_1')->default(false);
            $table->boolean('bomba_posos_2')->default(false);
            $table->boolean('bomba_planta_1')->default(false);
            $table->boolean('bomba_planta_2')->default(false);
            $table->boolean('bomba_planta_3')->default(false);
            $table->decimal('nivel_salmuera', 8, 2)->nullable();
            $table->decimal('nivel_tinaco', 8, 2)->nullable();
            $table->decimal('nivel_sisterna', 8, 2)->nullable();
            $table->decimal('presion_tuberia', 8, 2)->nullable();
            $table->decimal('nivel_hipoclorito', 8, 2)->nullable();
            $table->decimal('nivel_anticongelante', 8, 2)->nullable();
            $table->decimal('aceite_del_motor', 8, 2)->nullable();
            $table->decimal('tanque_diesel', 8, 2)->nullable();
            $table->decimal('voltaje_bateria', 8, 2)->nullable();
            $table->boolean('bomba_jockey')->default(false);
            $table->boolean('bomba_electrica')->default(false);
            $table->boolean('bomba_diesel')->default(false);
            $table->decimal('presion_tuberia_incendio', 8, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infra_bombas');
    }
};
