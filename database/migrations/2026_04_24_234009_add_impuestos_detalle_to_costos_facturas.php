<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5: el XML del CFDI es la fuente autoritativa de impuestos.
 * Se agregan los totales clave desnormalizados para operar/reportar
 * rápido, más un JSON con el detalle fiscal fino tal como viene del XML.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->decimal('iva_trasladado', 14, 2)->default(0)->after('iva');
            $table->decimal('iva_retenido', 14, 2)->default(0)->after('iva_trasladado');
            $table->decimal('isr_retenido', 14, 2)->default(0)->after('iva_retenido');
            $table->json('impuestos_detalle')->nullable()->after('isr_retenido');
        });
    }

    public function down(): void
    {
        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->dropColumn([
                'iva_trasladado',
                'iva_retenido',
                'isr_retenido',
                'impuestos_detalle',
            ]);
        });
    }
};
