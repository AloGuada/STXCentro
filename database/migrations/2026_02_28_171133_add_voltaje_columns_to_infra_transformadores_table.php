<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infra_transformadores', function (Blueprint $table) {
            $table->decimal('voltaje_a', 8, 2)->nullable()->after('date_C');
            $table->decimal('voltaje_b', 8, 2)->nullable()->after('voltaje_a');
            $table->decimal('voltaje_c', 8, 2)->nullable()->after('voltaje_b');
        });
    }

    public function down(): void
    {
        Schema::table('infra_transformadores', function (Blueprint $table) {
            $table->dropColumn(['voltaje_a', 'voltaje_b', 'voltaje_c']);
        });
    }
};
