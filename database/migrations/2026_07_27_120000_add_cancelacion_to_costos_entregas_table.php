<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_entregas', function (Blueprint $table) {
            // Persistimos si esta entrega marcó la factura como completa, para
            // poder revertir ese avance con precisión al cancelarla.
            $table->boolean('completa_factura')->default(false)->after('tipo');
            // Cancelación suave: la entrega conserva folio/PDF/historial pero deja
            // de contar para estatus/saldo.
            $table->timestamp('cancelada_at')->nullable()->after('completa_factura');
            $table->foreignUuid('cancelada_por')->nullable()->after('cancelada_at')->constrained('usuarios')->nullOnDelete();
            $table->string('motivo_cancelacion')->nullable()->after('cancelada_por');
        });
    }

    public function down(): void
    {
        Schema::table('costos_entregas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelada_por');
            $table->dropColumn(['completa_factura', 'cancelada_at', 'motivo_cancelacion']);
        });
    }
};
