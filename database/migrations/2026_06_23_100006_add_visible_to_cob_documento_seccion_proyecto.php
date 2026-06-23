<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cob_documento_seccion_proyecto', function (Blueprint $table) {
            $table->boolean('visible')->default(true)->after('estatus');
        });
    }

    public function down(): void
    {
        Schema::table('cob_documento_seccion_proyecto', function (Blueprint $table) {
            $table->dropColumn('visible');
        });
    }
};
