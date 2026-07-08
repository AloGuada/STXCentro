<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('costos_configuracion', function (Blueprint $table) {
            // Corte para la fecha de pago solicitada. Si está activo, aplica el
            // día/hora de corte (por defecto miércoles 1:00 PM); si no, se puede
            // elegir cualquier viernes futuro incluido el de la semana en curso.
            $table->boolean('corte_activo')->default(true);
            $table->unsignedTinyInteger('corte_dia')->default(3);
            $table->string('corte_hora', 5)->default('13:00');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_configuracion', function (Blueprint $table) {
            $table->dropColumn(['corte_activo', 'corte_dia', 'corte_hora']);
        });
    }
};
