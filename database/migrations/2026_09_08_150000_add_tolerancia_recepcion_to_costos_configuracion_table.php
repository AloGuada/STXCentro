<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuánto puede diferir el total del CFDI de lo que se está recibiendo sin
     * que la entrada se rechace. Hasta hoy era un centavo fijo en
     * `config('costos.epsilon_monto')`, y bastaba un redondeo del proveedor
     * para detener una recepción. Es una sola tolerancia en pesos, editable
     * desde Configuración de Costos; nace en un centavo para no cambiar el
     * comportamiento hasta que alguien decida subirla.
     */
    public function up(): void
    {
        Schema::table('costos_configuracion', function (Blueprint $table) {
            $table->decimal('tolerancia_recepcion', 10, 2)->default(0.01)->after('dia_comprobante_recepcion');
        });
    }

    public function down(): void
    {
        Schema::table('costos_configuracion', function (Blueprint $table) {
            $table->dropColumn('tolerancia_recepcion');
        });
    }
};
