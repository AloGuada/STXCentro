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
        Schema::table('costos_rubros_afectados', function (Blueprint $table) {
            $table->string('moneda', 3)->default('mxn')->after('monto');
            $table->decimal('monto_origen', 14, 2)->nullable()->after('moneda');
            $table->decimal('tipo_cambio', 14, 6)->default(1)->after('monto_origen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_rubros_afectados', function (Blueprint $table) {
            $table->dropColumn(['moneda', 'monto_origen', 'tipo_cambio']);
        });
    }
};
