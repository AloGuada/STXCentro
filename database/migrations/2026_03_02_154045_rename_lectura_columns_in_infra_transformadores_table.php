<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infra_transformadores', function (Blueprint $table) {
            $table->renameColumn('lectura_301', 'registro_a');
            $table->renameColumn('lectura_302', 'registro_b');
            $table->renameColumn('lectura_303', 'registro_c');
            $table->renameColumn('lectura_310', 'tarifa');
        });
    }

    public function down(): void
    {
        Schema::table('infra_transformadores', function (Blueprint $table) {
            $table->renameColumn('registro_a', 'lectura_301');
            $table->renameColumn('registro_b', 'lectura_302');
            $table->renameColumn('registro_c', 'lectura_303');
            $table->renameColumn('tarifa', 'lectura_310');
        });
    }
};
