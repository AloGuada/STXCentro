<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las unidades que compras dio por canceladas en una partida ya emitida.
 *
 * La cantidad pedida NO se toca: se conserva para que la orden siga
 * leyéndose como se emitió. Lo cancelado se resta al calcular el saldo por
 * recibir, el denominador de los porcentajes y el total de la orden.
 *
 * Sólo se escribe cuando el jefe de compras autoriza la cancelación; la
 * solicitud vive en `costos_oc_detalle_cancelaciones`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table): void {
            $table->decimal('cantidad_cancelada', 12, 4)->default(0)->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table): void {
            $table->dropColumn('cantidad_cancelada');
        });
    }
};
