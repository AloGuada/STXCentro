<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->unsignedSmallInteger('dias_credito')->nullable()->after('moneda');
            $table->string('base_dias_credito', 20)->default('factura')->after('dias_credito');
            $table->date('fecha_pago_calculada')->nullable()->after('base_dias_credito');
        });
    }

    public function down(): void
    {
        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->dropColumn(['dias_credito', 'base_dias_credito', 'fecha_pago_calculada']);
        });
    }
};
