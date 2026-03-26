<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_entregas', function (Blueprint $table) {
            $table->string('tipo', 20)->default('parcial')->after('observaciones');
        });
    }

    public function down(): void
    {
        Schema::table('costos_entregas', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
