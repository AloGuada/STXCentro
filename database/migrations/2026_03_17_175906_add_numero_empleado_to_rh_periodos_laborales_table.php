<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rh_periodos_laborales', 'numero_empleado')) {
            Schema::table('rh_periodos_laborales', function (Blueprint $table) {
                $table->string('numero_empleado')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('rh_periodos_laborales', function (Blueprint $table) {
            $table->dropColumn('numero_empleado');
        });
    }
};
