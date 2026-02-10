<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prod_fabricados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destajo_id')->constrained('prod_destajos')->cascadeOnDelete();
            $table->foreignId('pieza_id')->constrained('piezas')->cascadeOnDelete();
            $table->integer('cantidad')->default(1);
            $table->decimal('porcentual', 5, 2)->default(100)->comment('Porcentaje pagado en este destajo (0-100)');
            $table->decimal('precio_unitario_aplicado', 10, 2)->default(0)->comment('Precio por kg al momento');
            $table->decimal('total_calculado', 12, 2)->default(0)->comment('cantidad * peso_unitario * porcentual/100 * precio');
            $table->decimal('saldo_pendiente', 12, 2)->default(0)->comment('Monto que falta por pagar');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_fabricados');
    }
};
