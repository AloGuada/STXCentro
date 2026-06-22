<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las estimaciones vuelven a cobrarse a nivel obra: cada obra (base o adicional)
 * tiene sus propias estimaciones. Se respalda `obra_id` desde la obra base del
 * proyecto para cualquier estimación que aún apuntara solo al proyecto y se
 * elimina `proyecto_id` de la tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Asegura obra_id para estimaciones que vivían a nivel proyecto:
        // se asignan a la obra base (la más antigua) de su proyecto.
        DB::table('cob_estimaciones')
            ->whereNull('obra_id')
            ->whereNotNull('proyecto_id')
            ->orderBy('id')
            ->each(function ($estimacion): void {
                $obraBaseId = DB::table('obras')
                    ->where('proyecto_id', $estimacion->proyecto_id)
                    ->where('tipo', 'base')
                    ->orderBy('id')
                    ->value('id');

                if ($obraBaseId !== null) {
                    DB::table('cob_estimaciones')
                        ->where('id', $estimacion->id)
                        ->update(['obra_id' => $obraBaseId]);
                }
            });

        Schema::table('cob_estimaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proyecto_id');
        });
    }

    public function down(): void
    {
        Schema::table('cob_estimaciones', function (Blueprint $table) {
            $table->foreignId('proyecto_id')->nullable()->after('id')->constrained('proyectos')->nullOnDelete();
        });
    }
};
