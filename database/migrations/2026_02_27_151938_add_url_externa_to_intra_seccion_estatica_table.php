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
        Schema::table('intra_seccion_estatica', function (Blueprint $table) {
            $table->string('url_externa', 2048)->nullable()->after('boton');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('intra_seccion_estatica', function (Blueprint $table) {
            $table->dropColumn('url_externa');
        });
    }
};
