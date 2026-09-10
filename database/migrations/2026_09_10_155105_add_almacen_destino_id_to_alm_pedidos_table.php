<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El pedido de obra guarda a qué almacén va, no sólo a qué obra.
     *
     * Una obra puede tener varios almacenes (insumos, montaje, herramienta), y
     * con la obra sola la transferencia no sabía cuál llenar de destino. `obra_id`
     * se queda: se deriva del almacén y es la que filtran los listados y la que
     * decide si se surte con transferencia o con salida.
     *
     * Los pedidos que ya existen se rellenan sólo cuando su obra tiene un único
     * almacén activo; con varios no hay forma de saber a cuál iban, y la
     * transferencia los acepta a cualquiera de su obra.
     */
    public function up(): void
    {
        Schema::table('alm_pedidos', function (Blueprint $table) {
            $table->foreignId('almacen_destino_id')
                ->nullable()
                ->after('obra_id')
                ->constrained('alm_almacenes')
                ->nullOnDelete();
        });

        $unicoPorObra = DB::table('alm_almacenes')
            ->where('activo', true)
            ->whereNotNull('obra_id')
            ->groupBy('obra_id')
            ->havingRaw('count(*) = 1')
            ->selectRaw('obra_id, min(id) as id')
            ->pluck('id', 'obra_id');

        foreach ($unicoPorObra as $obraId => $almacenId) {
            DB::table('alm_pedidos')
                ->where('obra_id', $obraId)
                ->whereNull('almacen_destino_id')
                ->update(['almacen_destino_id' => $almacenId]);
        }
    }

    public function down(): void
    {
        Schema::table('alm_pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('almacen_destino_id');
        });
    }
};
