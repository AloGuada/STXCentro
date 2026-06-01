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
            $table->string('metodo_pago')->nullable()->after('moneda');
            $table->string('forma_pago')->nullable()->after('metodo_pago');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->dropColumn(['metodo_pago', 'forma_pago']);
        });
    }
};
