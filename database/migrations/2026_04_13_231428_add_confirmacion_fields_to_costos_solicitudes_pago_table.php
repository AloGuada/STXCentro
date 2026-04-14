<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_solicitudes_pago', function (Blueprint $table) {
            $table->boolean('confirmada_costos')->default(false)->after('estatus');
            $table->foreignUuid('confirmada_costos_por')->nullable()->after('confirmada_costos')->constrained('usuarios');
            $table->timestamp('confirmada_costos_at')->nullable()->after('confirmada_costos_por');
            $table->boolean('confirmada_contabilidad')->default(false)->after('confirmada_costos_at');
            $table->foreignUuid('confirmada_contabilidad_por')->nullable()->after('confirmada_contabilidad')->constrained('usuarios');
            $table->timestamp('confirmada_contabilidad_at')->nullable()->after('confirmada_contabilidad_por');
        });
    }

    public function down(): void
    {
        Schema::table('costos_solicitudes_pago', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmada_costos_por');
            $table->dropConstrainedForeignId('confirmada_contabilidad_por');
            $table->dropColumn(['confirmada_costos', 'confirmada_costos_at', 'confirmada_contabilidad', 'confirmada_contabilidad_at']);
        });
    }
};
