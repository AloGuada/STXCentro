<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_requisicion_cotizacion_precio', function (Blueprint $table) {
            $table->string('moneda', 10)->default('mxn')->after('precio_unitario');
        });
    }

    public function down(): void
    {
        Schema::table('costos_requisicion_cotizacion_precio', function (Blueprint $table) {
            $table->dropColumn('moneda');
        });
    }
};
