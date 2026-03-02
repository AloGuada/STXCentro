<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infra_tanques', function (Blueprint $table) {
            $table->renameColumn('presion_tanque_lp', 'nivel_tanque_lp');
        });
    }

    public function down(): void
    {
        Schema::table('infra_tanques', function (Blueprint $table) {
            $table->renameColumn('nivel_tanque_lp', 'presion_tanque_lp');
        });
    }
};
