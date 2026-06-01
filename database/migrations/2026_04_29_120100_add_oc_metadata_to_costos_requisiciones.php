<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->date('fecha_entrega_esperada')->nullable()->after('fecha_requerida');
            $table->text('notas_oc')->nullable()->after('justificacion');
            $table->string('modo_pago', 20)->nullable()->after('notas_oc');
        });
    }

    public function down(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->dropColumn(['fecha_entrega_esperada', 'notas_oc', 'modo_pago']);
        });
    }
};
