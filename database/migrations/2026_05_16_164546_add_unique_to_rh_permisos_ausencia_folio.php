<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_permisos_ausencia', function (Blueprint $table) {
            $table->unique('folio');
        });
    }

    public function down(): void
    {
        Schema::table('rh_permisos_ausencia', function (Blueprint $table) {
            $table->dropUnique(['folio']);
        });
    }
};
