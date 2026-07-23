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
        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->decimal('tipo_cambio', 14, 6)->default(1)->after('moneda');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->dropColumn('tipo_cambio');
        });
    }
};
