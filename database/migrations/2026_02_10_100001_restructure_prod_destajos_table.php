<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prod_destajos', function (Blueprint $table) {
            $table->dropForeign(['grupo_id']);
            $table->dropColumn(['grupo_id', 'observaciones']);
        });

        Schema::table('prod_destajos', function (Blueprint $table) {
            $table->renameColumn('cerrado', 'cerrada');
        });

        Schema::table('prod_destajos', function (Blueprint $table) {
            $table->integer('semana')->change();
            $table->double('cantidad')->nullable()->after('cerrada');
            $table->datetime('fecha_cierre')->nullable()->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('prod_destajos', function (Blueprint $table) {
            $table->dropColumn(['cantidad', 'fecha_cierre']);
        });

        Schema::table('prod_destajos', function (Blueprint $table) {
            $table->renameColumn('cerrada', 'cerrado');
        });

        Schema::table('prod_destajos', function (Blueprint $table) {
            $table->date('semana')->change();
            $table->foreignId('grupo_id')->constrained('prod_grupos')->cascadeOnDelete();
            $table->text('observaciones')->nullable();
        });
    }
};
