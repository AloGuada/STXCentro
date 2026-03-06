<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_requisiciones', function (Blueprint $table) {
            $table->enum('tipo_contrato_generado', ['planta', 'obra'])->default('planta')->after('tipo_requisicion');
        });
    }

    public function down(): void
    {
        Schema::table('rh_requisiciones', function (Blueprint $table) {
            $table->dropColumn('tipo_contrato_generado');
        });
    }
};
