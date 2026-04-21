<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_periodos_laborales', function (Blueprint $table) {
            $table->string('sueldo_mensual', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rh_periodos_laborales', function (Blueprint $table) {
            $table->decimal('sueldo_mensual', 12, 2)->nullable()->change();
        });
    }
};
