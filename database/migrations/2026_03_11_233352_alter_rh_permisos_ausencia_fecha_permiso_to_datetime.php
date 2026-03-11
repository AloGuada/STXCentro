<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_permisos_ausencia', function (Blueprint $table): void {
            $table->dateTime('fecha_permiso')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rh_permisos_ausencia', function (Blueprint $table): void {
            $table->date('fecha_permiso')->nullable()->change();
        });
    }
};
