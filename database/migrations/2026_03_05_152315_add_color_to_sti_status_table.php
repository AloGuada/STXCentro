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
        Schema::table('sti_status', function (Blueprint $table) {
            $table->string('color', 7)->default('#3b82f6')->after('detiene_tiempo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sti_status', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
