<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('costos_solicitudes_pago', function (Blueprint $table) {
            $table->dropColumn('justificacion');
            $table->string('tipo_moneda')->default('mxn')->after('tipo_pago');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_solicitudes_pago', function (Blueprint $table) {
            $table->dropColumn('tipo_moneda');
            $table->text('justificacion')->nullable()->after('concepto');
        });
    }
};
