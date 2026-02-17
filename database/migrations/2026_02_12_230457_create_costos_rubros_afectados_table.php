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
        Schema::create('costos_rubros_afectados', function (Blueprint $table) {
            $table->id();
            $table->string('entrada_type');
            $table->unsignedBigInteger('entrada_id');
            $table->foreignId('obra_rubro_id')->constrained('costos_obra_rubros');
            $table->decimal('monto', 14, 2);
            $table->boolean('sobre_giro')->default(false);
            $table->text('descripcion')->nullable();
            $table->string('tipo_movimiento');
            $table->string('estatus')->default('pendiente');
            $table->foreignUuid('usuario_aplica_id')->nullable()->constrained('usuarios');
            $table->timestamp('fecha_aplicacion')->nullable();
            $table->timestamps();

            $table->index(['entrada_type', 'entrada_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_rubros_afectados');
    }
};
