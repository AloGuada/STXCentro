<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renombra costos_aprobaciones_solicitud -> costos_aprobaciones y agrega
 * columnas polimorficas (aprobable_type, aprobable_id). El campo solicitud_id
 * queda como nullable durante la transicion para no romper datos existentes;
 * se respalda mediante backfill a las nuevas columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('costos_aprobaciones_solicitud', 'costos_aprobaciones');

        Schema::table('costos_aprobaciones', function (Blueprint $table) {
            $table->string('aprobable_type')->nullable()->after('id');
            $table->unsignedBigInteger('aprobable_id')->nullable()->after('aprobable_type');
            $table->index(['aprobable_type', 'aprobable_id']);
        });

        // Backfill: rows existentes vienen todas de SolicitudPago.
        DB::table('costos_aprobaciones')
            ->whereNotNull('solicitud_id')
            ->update([
                'aprobable_type' => 'App\\Models\\Costos\\SolicitudPago',
                'aprobable_id' => DB::raw('solicitud_id'),
            ]);

        // solicitud_id queda como nullable para facilitar futuro drop sin perder datos.
        Schema::table('costos_aprobaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('solicitud_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('costos_aprobaciones', function (Blueprint $table) {
            $table->dropIndex(['aprobable_type', 'aprobable_id']);
            $table->dropColumn(['aprobable_type', 'aprobable_id']);
            $table->unsignedBigInteger('solicitud_id')->nullable(false)->change();
        });

        Schema::rename('costos_aprobaciones', 'costos_aprobaciones_solicitud');
    }
};
