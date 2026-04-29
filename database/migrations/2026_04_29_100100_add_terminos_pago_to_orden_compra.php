<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Captura los terminos de pago al momento de generar la OC. Heredados
 * desde el proveedor pero overrideables: un proveedor de credito puede
 * tener una compra puntual de contado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->string('tipo_pago')->default('credito')->after('moneda'); // credito | contado
            $table->unsignedSmallInteger('dias_credito')->default(0)->after('tipo_pago');
            $table->string('forma_pago')->default('transferencia')->after('dias_credito');
        });
    }

    public function down(): void
    {
        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->dropColumn(['tipo_pago', 'dias_credito', 'forma_pago']);
        });
    }
};
