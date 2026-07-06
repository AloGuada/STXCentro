<?php

use App\Models\Obra;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->foreignId('presupuesto_id')->nullable()->after('obra_id')
                ->constrained('costos_presupuestos')->nullOnDelete();
        });

        // Backfill: la requisición hereda el presupuesto (type=Obra) de su obra.
        $obraIds = DB::table('costos_requisiciones')
            ->whereNotNull('obra_id')
            ->distinct()
            ->pluck('obra_id');

        foreach ($obraIds as $obraId) {
            $presupuestoId = DB::table('costos_presupuestos')
                ->where('presupuestable_type', Obra::class)
                ->where('presupuestable_id', $obraId)
                ->value('id');

            if ($presupuestoId) {
                DB::table('costos_requisiciones')
                    ->where('obra_id', $obraId)
                    ->update(['presupuesto_id' => $presupuestoId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->dropForeign(['presupuesto_id']);
            $table->dropColumn('presupuesto_id');
        });
    }
};
