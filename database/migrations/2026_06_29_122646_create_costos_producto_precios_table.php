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
        Schema::create('costos_producto_precios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('costos_productos')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->decimal('precio', 14, 2);
            $table->string('moneda')->default('mxn');
            $table->date('fecha');
            // Requisición de origen del precio (cuando proviene de una cotización).
            $table->foreignId('requisicion_id')->nullable()->constrained('costos_requisiciones')->nullOnDelete();
            $table->timestamps();

            $table->index(['producto_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_producto_precios');
    }
};
