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
        Schema::create('costos_rubro_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_rubro_id')->constrained('costos_obra_rubros')->cascadeOnDelete();
            $table->foreignId('rubro_afectado_id')->nullable()->constrained('costos_rubros_afectados')->nullOnDelete();
            $table->string('tipo');
            $table->decimal('monto', 14, 2);
            $table->decimal('saldo_antes', 14, 2);
            $table->decimal('saldo_despues', 14, 2);
            $table->string('motivo')->nullable();
            $table->foreignUuid('usuario_id')->nullable()->constrained('usuarios');
            $table->timestamps();

            $table->index(['obra_rubro_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_rubro_movimientos');
    }
};
