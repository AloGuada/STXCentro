<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_requisicion_cotizacion_precio', function (Blueprint $table) {
            $table->foreignId('opcion_id')->nullable()->after('proveedor_id')
                ->constrained('costos_requisicion_cotizacion_opcion')->cascadeOnDelete();
            // Descripción opcional por celda (ej. "Taladro Makita" vs "Black&Decker").
            $table->string('descripcion')->nullable()->after('precio_unitario');
        });

        // Backfill: las cotizaciones existentes se agrupan en una opción por
        // (requisición, proveedor).
        $pares = DB::table('costos_requisicion_cotizacion_precio as cp')
            ->join('costos_requisicion_detalle as d', 'd.id', '=', 'cp.requisicion_detalle_id')
            ->select('d.requisicion_id', 'cp.proveedor_id')
            ->distinct()
            ->get();

        foreach ($pares as $par) {
            $opcionId = DB::table('costos_requisicion_cotizacion_opcion')->insertGetId([
                'requisicion_id' => $par->requisicion_id,
                'proveedor_id' => $par->proveedor_id,
                'etiqueta' => null,
                'orden' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('costos_requisicion_cotizacion_precio')
                ->where('proveedor_id', $par->proveedor_id)
                ->whereIn('requisicion_detalle_id', function ($q) use ($par) {
                    $q->select('id')->from('costos_requisicion_detalle')->where('requisicion_id', $par->requisicion_id);
                })
                ->update(['opcion_id' => $opcionId]);
        }

        // El eje de unicidad pasa de (partida, proveedor) a (partida, opción).
        Schema::table('costos_requisicion_cotizacion_precio', function (Blueprint $table) {
            $table->dropUnique('costos_req_cot_precio_partida_proveedor_unique');
            $table->unique(['requisicion_detalle_id', 'opcion_id'], 'costos_req_cot_precio_partida_opcion_unique');
        });
    }

    public function down(): void
    {
        Schema::table('costos_requisicion_cotizacion_precio', function (Blueprint $table) {
            $table->dropUnique('costos_req_cot_precio_partida_opcion_unique');
            $table->dropConstrainedForeignId('opcion_id');
            $table->dropColumn('descripcion');
            $table->unique(['requisicion_detalle_id', 'proveedor_id'], 'costos_req_cot_precio_partida_proveedor_unique');
        });
    }
};
