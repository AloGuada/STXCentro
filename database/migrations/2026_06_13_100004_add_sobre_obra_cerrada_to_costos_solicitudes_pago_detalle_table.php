<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_solicitudes_pago_detalle', function (Blueprint $table) {
            $table->boolean('sobre_obra_cerrada')->default(false)->after('obra_rubro_id');
        });
    }

    public function down(): void
    {
        Schema::table('costos_solicitudes_pago_detalle', function (Blueprint $table) {
            $table->dropColumn('sobre_obra_cerrada');
        });
    }
};
