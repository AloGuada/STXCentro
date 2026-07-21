<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_solicitudes_pago', function (Blueprint $table) {
            $table->string('comentarios', 250)->nullable()->after('concepto');
        });
    }

    public function down(): void
    {
        Schema::table('costos_solicitudes_pago', function (Blueprint $table) {
            $table->dropColumn('comentarios');
        });
    }
};
