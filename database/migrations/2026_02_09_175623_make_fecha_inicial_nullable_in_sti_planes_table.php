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
        Schema::table('sti_planes', function (Blueprint $table) {
            $table->date('fecha_inicial')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sti_planes', function (Blueprint $table) {
            $table->date('fecha_inicial')->nullable(false)->change();
        });
    }
};
