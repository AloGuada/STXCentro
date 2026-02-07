<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite no soporta ALTER COLUMN directamente, recreamos la tabla
        Schema::table('sti_mantenimientos', function (Blueprint $table) {
            $table->foreignId('tecnico_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sti_mantenimientos', function (Blueprint $table) {
            $table->foreignId('tecnico_id')->nullable(false)->change();
        });
    }
};
