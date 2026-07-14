<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_solicitudes_pago', function (Blueprint $table) {
            $table->foreignUuid('firma_adicional_aprobador_id')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete();
        });

        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->foreignUuid('firma_adicional_aprobador_id')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('costos_solicitudes_pago', function (Blueprint $table) {
            $table->dropConstrainedForeignId('firma_adicional_aprobador_id');
        });

        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('firma_adicional_aprobador_id');
        });
    }
};
