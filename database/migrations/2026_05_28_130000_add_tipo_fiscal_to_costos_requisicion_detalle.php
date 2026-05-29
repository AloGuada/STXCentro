<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_requisicion_detalle', function (Blueprint $table) {
            $table->string('tipo_fiscal')->default('mercancia')->after('uso_cfdi_id');
        });
    }

    public function down(): void
    {
        Schema::table('costos_requisicion_detalle', function (Blueprint $table) {
            $table->dropColumn('tipo_fiscal');
        });
    }
};
