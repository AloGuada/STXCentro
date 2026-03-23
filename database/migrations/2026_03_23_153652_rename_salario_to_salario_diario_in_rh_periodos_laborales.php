<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_periodos_laborales', function (Blueprint $table) {
            $table->renameColumn('salario', 'salario_diario');
        });
    }

    public function down(): void
    {
        Schema::table('rh_periodos_laborales', function (Blueprint $table) {
            $table->renameColumn('salario_diario', 'salario');
        });
    }
};
