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
        Schema::table('costos_obra_rubros', function (Blueprint $table) {
            $table->decimal('apartado', 14, 2)->default(0)->after('acumulado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_obra_rubros', function (Blueprint $table) {
            $table->dropColumn('apartado');
        });
    }
};
