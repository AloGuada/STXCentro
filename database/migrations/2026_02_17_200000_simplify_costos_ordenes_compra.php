<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->dropColumn(['tipo_cambio', 'condiciones_pago', 'subtotal', 'iva']);
        });

        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->dropColumn(['descripcion', 'unidad', 'cantidad', 'precio_unitario', 'subtotal', 'cantidad_recibida']);
            $table->decimal('monto', 14, 2)->after('obra_rubro_id');
        });

        Schema::dropIfExists('costos_entrega_detalle');
    }

    public function down(): void
    {
        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->decimal('tipo_cambio', 10, 4)->default(1);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('iva', 14, 2)->default(0);
            $table->string('condiciones_pago')->nullable();
        });

        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->dropColumn('monto');
            $table->string('descripcion')->default('');
            $table->string('unidad', 20)->default('pza');
            $table->decimal('cantidad', 12, 2)->default(0);
            $table->decimal('precio_unitario', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('cantidad_recibida', 12, 2)->default(0);
        });

        Schema::create('costos_entrega_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_id')->constrained('costos_entregas')->cascadeOnDelete();
            $table->foreignId('orden_compra_detalle_id')->constrained('costos_ordenes_compra_detalle');
            $table->decimal('cantidad_recibida', 12, 2);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }
};
