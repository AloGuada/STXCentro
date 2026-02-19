<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('obra_id')->constrained('obras');
            $table->foreignId('departamento_id')->constrained('departamentos');
            $table->foreignUuid('creado_por')->constrained('usuarios');
            $table->string('moneda', 10)->default('mxn');
            $table->decimal('tipo_cambio', 10, 4)->default(1);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('iva', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('condiciones_pago')->nullable();
            $table->date('fecha_entrega_esperada')->nullable();
            $table->text('notas')->nullable();
            $table->string('estatus')->default('borrador');
            $table->string('pdf_formato_path')->nullable();
            $table->string('pdf_firmado_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_ordenes_compra');
    }
};
