<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->boolean('completamente_entregada')->default(false)->after('estatus');
        });
    }

    public function down(): void
    {
        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->dropColumn('completamente_entregada');
        });
    }
};
