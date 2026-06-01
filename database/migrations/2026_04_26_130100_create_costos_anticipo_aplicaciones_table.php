<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_anticipo_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anticipo_id')->constrained('costos_anticipos')->cascadeOnDelete();
            $table->foreignId('factura_id')->constrained('costos_facturas')->cascadeOnDelete();
            $table->decimal('monto', 14, 2);
            $table->date('fecha');
            $table->foreignUuid('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['anticipo_id', 'factura_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_anticipo_aplicaciones');
    }
};
