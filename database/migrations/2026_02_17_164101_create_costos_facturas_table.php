<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_facturas', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('orden_compra_id')->constrained('costos_ordenes_compra');
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->string('uuid_fiscal')->nullable();
            $table->string('folio_fiscal')->nullable();
            $table->string('ruta_xml')->nullable();
            $table->string('ruta_pdf')->nullable();
            $table->decimal('subtotal', 14, 2);
            $table->decimal('iva', 14, 2);
            $table->decimal('total', 14, 2);
            $table->string('moneda', 10)->default('mxn');
            $table->date('fecha_factura')->nullable();
            $table->string('estatus')->default('pendiente_entrega');
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_facturas');
    }
};
