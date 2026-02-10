<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piezas', function (Blueprint $table) {
            $table->dropColumn(['plano', 'peso_total', 'observaciones']);
        });

        Schema::table('piezas', function (Blueprint $table) {
            $table->renameColumn('largo', 'longitud');
            $table->renameColumn('peso_unitario', 'peso');
        });

        Schema::table('piezas', function (Blueprint $table) {
            $table->integer('version')->default(1)->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('piezas', function (Blueprint $table) {
            $table->dropColumn('version');
        });

        Schema::table('piezas', function (Blueprint $table) {
            $table->renameColumn('longitud', 'largo');
            $table->renameColumn('peso', 'peso_unitario');
        });

        Schema::table('piezas', function (Blueprint $table) {
            $table->string('plano')->nullable()->after('obra_id');
            $table->decimal('peso_total', 12, 2)->default(0)->after('cantidad');
            $table->string('observaciones')->nullable()->after('peso_total');
        });
    }
};
