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
        Schema::table('costos_obra_rubros', function (Blueprint $table) {
            $table->foreignId('presupuesto_id')->nullable()->after('id')
                ->constrained('costos_presupuestos')->cascadeOnDelete();
        });

        $this->backfill();

        // Se conserva obra_id (nullable) como columna de compatibilidad durante la
        // transición; se elimina en un PR de limpieza posterior.
        Schema::table('costos_obra_rubros', function (Blueprint $table) {
            $table->unsignedBigInteger('obra_id')->nullable()->change();
        });
    }

    /**
     * Crea un Presupuesto por cada obra con rubros, copiando su estatus
     * (obra cerrada -> presupuesto cerrado), y enlaza los rubros existentes.
     */
    private function backfill(): void
    {
        $now = now();

        $obraIds = DB::table('costos_obra_rubros')
            ->whereNotNull('obra_id')
            ->distinct()
            ->pluck('obra_id');

        foreach ($obraIds as $obraId) {
            $estatusObra = DB::table('obras')->where('id', $obraId)->value('estatus');

            $presupuestoId = DB::table('costos_presupuestos')->insertGetId([
                'presupuestable_type' => Obra::class,
                'presupuestable_id' => $obraId,
                'nombre_interno' => null,
                'estatus' => $estatusObra === 'cerrada' ? 'cerrado' : 'activo',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('costos_obra_rubros')
                ->where('obra_id', $obraId)
                ->update(['presupuesto_id' => $presupuestoId]);
        }
    }

    public function down(): void
    {
        Schema::table('costos_obra_rubros', function (Blueprint $table) {
            $table->dropForeign(['presupuesto_id']);
            $table->dropColumn('presupuesto_id');
        });
    }
};
