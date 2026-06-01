<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_permisos', function (Blueprint $table) {
            $table->string('tipo_aprobacion')->default('solicitud_pago')->after('nivel');
            $table->index('tipo_aprobacion');
        });
    }

    public function down(): void
    {
        Schema::table('costos_permisos', function (Blueprint $table) {
            $table->dropIndex(['tipo_aprobacion']);
            $table->dropColumn('tipo_aprobacion');
        });
    }
};
