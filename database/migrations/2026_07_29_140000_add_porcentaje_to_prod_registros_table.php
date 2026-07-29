<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite pagar un avance parcial de las piezas: se captura la cantidad y
     * el porcentaje pagado, y el resto se liquida en un destajo posterior.
     * Todo lo capturado hasta hoy se considera pagado al 100%.
     */
    public function up(): void
    {
        Schema::table('prod_registros', function (Blueprint $table) {
            $table->decimal('porcentaje', 5, 2)->default(100)->after('cantidad');
        });

        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->decimal('porcentaje', 5, 2)->default(100)->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('prod_registros', function (Blueprint $table) {
            $table->dropColumn('porcentaje');
        });

        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->dropColumn('porcentaje');
        });
    }
};
