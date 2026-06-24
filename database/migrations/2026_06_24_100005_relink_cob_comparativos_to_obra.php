<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El comparativo vuelve a ligarse a una obra (además del proyecto): el de la
        // obra base reemplaza el presupuesto (en unitario) y los de adicionales suman.
        // La gestión sigue a nivel proyecto (el obra_id se elige en el formulario).
        Schema::table('cob_comparativos', function (Blueprint $table) {
            $table->foreignId('obra_id')->nullable()->after('proyecto_id')->constrained('obras')->cascadeOnDelete();
        });

        // Backfill: los comparativos existentes quedan apuntando a la obra BASE de su
        // proyecto. Los que en realidad son de adicionales se re-asignan a mano desde
        // el nuevo selector.
        DB::statement("
            UPDATE cob_comparativos
            SET obra_id = (
                SELECT o.id FROM obras o
                WHERE o.proyecto_id = cob_comparativos.proyecto_id AND o.tipo = 'base'
                ORDER BY o.id ASC LIMIT 1
            )
            WHERE obra_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('cob_comparativos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('obra_id');
        });
    }
};
