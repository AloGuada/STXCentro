<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obras', function (Blueprint $table) {
            $table->date('fecha_inicio')->nullable()->after('descripcion');
            $table->date('fecha_fin')->nullable()->after('fecha_inicio');
            $table->decimal('presupuesto_total', 14, 2)->default(0)->after('fecha_fin');
            $table->string('estatus')->default('planificacion')->after('presupuesto_total');
        });
    }

    public function down(): void
    {
        Schema::table('obras', function (Blueprint $table) {
            $table->dropColumn(['fecha_inicio', 'fecha_fin', 'presupuesto_total', 'estatus']);
        });
    }
};
