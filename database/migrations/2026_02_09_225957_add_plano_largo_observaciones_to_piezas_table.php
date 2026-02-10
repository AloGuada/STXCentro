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
        Schema::table('piezas', function (Blueprint $table) {
            $table->string('plano')->nullable()->after('obra_id');
            $table->decimal('largo', 10, 2)->nullable()->after('descripcion');
            $table->string('observaciones')->nullable()->after('peso_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('piezas', function (Blueprint $table) {
            $table->dropColumn(['plano', 'largo', 'observaciones']);
        });
    }
};
