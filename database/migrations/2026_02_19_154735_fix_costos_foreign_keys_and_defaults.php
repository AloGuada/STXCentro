<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // 1. Fix obra_id FK en costos_ordenes_compra (puede haberse perdido con ->change() en MySQL)
        if ($driver !== 'sqlite') {
            $fkExists = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'costos_ordenes_compra'
                  AND COLUMN_NAME = 'obra_id'
                  AND REFERENCED_TABLE_NAME = 'obras'
            ");

            if (empty($fkExists)) {
                Schema::table('costos_ordenes_compra', function (Blueprint $table) {
                    $table->foreign('obra_id')->references('id')->on('obras')->nullOnDelete();
                });
            }
        }

        // 2. Fix monto default en costos_ordenes_compra_detalle
        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->decimal('monto', 14, 2)->default(0)->change();
        });

        // 3. Fix obra_rubro_id FK sin onDelete en costos_ordenes_compra_detalle
        if ($driver !== 'sqlite') {
            Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
                $table->dropForeign(['obra_rubro_id']);
                $table->foreign('obra_rubro_id')->references('id')->on('costos_obra_rubros')->nullOnDelete();
            });

            Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
                $table->unsignedBigInteger('obra_rubro_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // Revert monto default
        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->decimal('monto', 14, 2)->change();
        });

        // Revert obra_rubro_id FK
        if ($driver !== 'sqlite') {
            Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
                $table->dropForeign(['obra_rubro_id']);
                $table->foreign('obra_rubro_id')->references('id')->on('costos_obra_rubros');
            });

            Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
                $table->unsignedBigInteger('obra_rubro_id')->nullable(false)->change();
            });
        }
    }
};
