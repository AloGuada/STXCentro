<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El proyecto queda como paraguas comercial + cronograma (Gantt). Los datos
 * contractuales/financieros (tipo de contrato, monto, IVA, anticipo, garantía)
 * viven en cada obra. Se eliminan esas columnas de `proyectos`; permanecen
 * `no`, `descripcion`, `cliente_id`, `estatus`, `activa`, `fecha_inicio_plan`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->dropColumn(['tipo_contrato', 'monto', 'monto_iva', 'anticipo', 'garantia']);
        });
    }

    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->string('tipo_contrato')->nullable()->after('cliente_id');
            $table->decimal('monto', 15, 2)->nullable()->after('tipo_contrato');
            $table->decimal('monto_iva', 15, 2)->nullable()->after('monto');
            $table->decimal('anticipo', 15, 2)->nullable()->after('monto_iva');
            $table->decimal('garantia', 15, 2)->nullable()->after('anticipo');
        });
    }
};
