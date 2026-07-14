<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_aprobaciones', function (Blueprint $table) {
            $table->boolean('es_adicional')->default(false)->after('nivel');
        });
    }

    public function down(): void
    {
        Schema::table('costos_aprobaciones', function (Blueprint $table) {
            $table->dropColumn('es_adicional');
        });
    }
};
