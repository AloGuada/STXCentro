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
        Schema::create('infra_ptar', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('usuario_id')->nullable()->constrained('usuarios');
            $table->boolean('soplador_activa')->default(false);
            $table->boolean('bomba_activa')->default(false);
            $table->decimal('nivel_cloro', 8, 2)->nullable();
            $table->boolean('trampa_solida')->default(false);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infra_ptar');
    }
};
