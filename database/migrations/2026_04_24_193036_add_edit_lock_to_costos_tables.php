<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'costos_ordenes_compra',
        'costos_facturas',
        'costos_pagos',
        'costos_solicitudes_pago',
        'costos_afectaciones_presupuestales',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignUuid('locked_by')
                    ->nullable()
                    ->after('updated_at')
                    ->constrained('usuarios')
                    ->nullOnDelete();
                $blueprint->timestamp('locked_at')->nullable()->after('locked_by');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('locked_by');
                $blueprint->dropColumn('locked_at');
            });
        }
    }
};
