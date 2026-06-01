<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->foreignId('obra_id')->nullable()->after('departamento_id')
                ->constrained('obras')->nullOnDelete();
        });

        // Backfill best-effort: la obra se toma del obra_rubro de la primera
        // partida de cada requisición existente.
        $filas = DB::table('costos_requisicion_detalle as d')
            ->join('costos_obra_rubros as orub', 'orub.id', '=', 'd.obra_rubro_id')
            ->select('d.requisicion_id', 'orub.obra_id')
            ->whereNotNull('d.obra_rubro_id')
            ->orderBy('d.id')
            ->get()
            ->unique('requisicion_id');

        foreach ($filas as $fila) {
            DB::table('costos_requisiciones')
                ->where('id', $fila->requisicion_id)
                ->update(['obra_id' => $fila->obra_id]);
        }
    }

    public function down(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('obra_id');
        });
    }
};
