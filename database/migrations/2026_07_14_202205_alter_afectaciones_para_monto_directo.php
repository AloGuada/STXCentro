<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La afectación directa ya no captura departamento; se vuelve opcional.
        Schema::table('costos_afectaciones_presupuestales', function (Blueprint $table) {
            $table->unsignedBigInteger('departamento_id')->nullable()->change();
        });

        // El detalle ahora captura el monto a afectar directamente; cantidad,
        // precio_unitario y concepto quedan opcionales.
        Schema::table('costos_afectaciones_detalle', function (Blueprint $table) {
            $table->decimal('cantidad', 14, 2)->nullable()->change();
            $table->decimal('precio_unitario', 14, 2)->nullable()->change();
            $table->string('concepto')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('costos_afectaciones_presupuestales', function (Blueprint $table) {
            $table->unsignedBigInteger('departamento_id')->nullable(false)->change();
        });

        Schema::table('costos_afectaciones_detalle', function (Blueprint $table) {
            $table->decimal('cantidad', 14, 2)->nullable(false)->change();
            $table->decimal('precio_unitario', 14, 2)->nullable(false)->change();
            $table->string('concepto')->nullable(false)->change();
        });
    }
};
