<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_anticipos', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('obra_id')->nullable()->constrained('obras')->nullOnDelete();
            $table->decimal('monto', 14, 2);
            $table->decimal('saldo_disponible', 14, 2);
            $table->string('moneda', 10)->default('mxn');
            $table->string('estatus')->default('vigente');
            $table->string('referencia')->nullable();
            $table->date('fecha');
            $table->text('notas')->nullable();
            $table->foreignUuid('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignUuid('locked_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index(['proveedor_id', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_anticipos');
    }
};
