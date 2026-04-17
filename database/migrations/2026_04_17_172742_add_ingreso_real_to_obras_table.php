<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obras', function (Blueprint $table) {
            $table->decimal('ingreso_real', 16, 4)->nullable()->after('presupuesto_total');
        });
    }

    public function down(): void
    {
        Schema::table('obras', function (Blueprint $table) {
            $table->dropColumn('ingreso_real');
        });
    }
};
