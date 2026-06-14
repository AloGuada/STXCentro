<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->boolean('sobre_obra_cerrada')->default(false)->after('estatus');
        });
    }

    public function down(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->dropColumn('sobre_obra_cerrada');
        });
    }
};
