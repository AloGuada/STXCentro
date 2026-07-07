<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_presupuestos', function (Blueprint $table) {
            $table->string('op_interno')->nullable()->after('nombre_interno');
        });
    }

    public function down(): void
    {
        Schema::table('costos_presupuestos', function (Blueprint $table) {
            $table->dropColumn('op_interno');
        });
    }
};
