<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->boolean('aprobada_costos')->default(false)->after('notas');
            $table->uuid('aprobada_costos_por')->nullable()->after('aprobada_costos');
            $table->timestamp('aprobada_costos_at')->nullable()->after('aprobada_costos_por');

            $table->foreign('aprobada_costos_por')->references('id')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->dropForeign(['aprobada_costos_por']);
            $table->dropColumn(['aprobada_costos', 'aprobada_costos_por', 'aprobada_costos_at']);
        });
    }
};
