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
        Schema::table('costos_pagos', function (Blueprint $table) {
            $table->decimal('monto_mxn', 14, 2)->nullable()->after('tipo_cambio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_pagos', function (Blueprint $table) {
            $table->dropColumn('monto_mxn');
        });
    }
};
