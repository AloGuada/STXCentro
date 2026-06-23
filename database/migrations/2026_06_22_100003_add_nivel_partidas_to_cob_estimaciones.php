<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estimaciones multi-nivel: una estimación se ancla siempre al proyecto y puede
 * ser global (sin obra), de obra, o de obra + partidas. `obra_id` pasa a ser
 * nullable; se agrega `proyecto_id` (ancla) y `nivel`. Las existentes se quedan
 * en nivel 'obra' con su `proyecto_id` derivado de la obra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cob_estimaciones', function (Blueprint $table) {
            $table->foreignId('proyecto_id')->nullable()->after('id')->constrained('proyectos')->nullOnDelete();
            $table->string('nivel')->default('obra')->after('obra_id');
        });

        // Backfill: las estimaciones actuales son de obra → heredan su proyecto.
        DB::statement('UPDATE cob_estimaciones SET proyecto_id = (SELECT o.proyecto_id FROM obras o WHERE o.id = cob_estimaciones.obra_id) WHERE proyecto_id IS NULL AND obra_id IS NOT NULL');

        Schema::table('cob_estimaciones', function (Blueprint $table) {
            $table->foreignId('obra_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cob_estimaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proyecto_id');
            $table->dropColumn('nivel');
        });
    }
};
