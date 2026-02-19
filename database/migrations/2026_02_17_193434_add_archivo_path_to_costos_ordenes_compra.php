<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->string('archivo_path')->nullable()->after('notas');
        });
    }

    public function down(): void
    {
        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->dropColumn('archivo_path');
        });
    }
};
