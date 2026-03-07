<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_requisiciones', function (Blueprint $table) {
            $table->boolean('procesar_ia')->default(false)->after('tipo_contrato_generado');
        });
    }

    public function down(): void
    {
        Schema::table('rh_requisiciones', function (Blueprint $table) {
            $table->dropColumn('procesar_ia');
        });
    }
};
