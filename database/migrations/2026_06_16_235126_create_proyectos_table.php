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
        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->string('no');
            $table->string('descripcion');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('tipo_contrato')->nullable();
            $table->decimal('monto', 15, 2)->nullable();
            $table->decimal('monto_iva', 15, 2)->nullable();
            $table->decimal('anticipo', 15, 2)->nullable();
            $table->decimal('garantia', 15, 2)->nullable();
            $table->string('estatus')->default('abierta');
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index('cliente_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proyectos');
    }
};
