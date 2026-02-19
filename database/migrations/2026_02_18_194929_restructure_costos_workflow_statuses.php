<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->boolean('aceptada_contabilidad')->default(false)->after('aprobada_costos_at');
            $table->foreignUuid('aceptada_contabilidad_por')->nullable()->after('aceptada_contabilidad')->constrained('usuarios')->nullOnDelete();
            $table->timestamp('aceptada_contabilidad_at')->nullable()->after('aceptada_contabilidad_por');
        });

        // Facturas with pago pagado -> pagada + aceptada_contabilidad
        DB::table('costos_facturas')
            ->whereIn('id', function ($query) {
                $query->select('pagable_id')
                    ->from('costos_pagos')
                    ->where('pagable_type', 'App\\Models\\Costos\\Factura')
                    ->where('estatus', 'pagado')
                    ->whereNull('pago_padre_id');
            })
            ->update([
                'estatus' => 'pagada',
                'aceptada_contabilidad' => true,
            ]);

        // Facturas with pago existing (not pagado) -> aceptada_contabilidad + pendiente_pago
        DB::table('costos_facturas')
            ->whereIn('id', function ($query) {
                $query->select('pagable_id')
                    ->from('costos_pagos')
                    ->where('pagable_type', 'App\\Models\\Costos\\Factura')
                    ->where('estatus', '!=', 'pagado')
                    ->whereNull('pago_padre_id');
            })
            ->where('estatus', '!=', 'pagada')
            ->update([
                'estatus' => 'pendiente_pago',
                'aceptada_contabilidad' => true,
            ]);

        // entrega_completa + aprobada_costos -> pendiente_pago
        DB::table('costos_facturas')
            ->where('estatus', 'entrega_completa')
            ->where('aprobada_costos', true)
            ->update(['estatus' => 'pendiente_pago']);

        // entrega_completa + not aprobada_costos -> pendiente_entrega
        DB::table('costos_facturas')
            ->where('estatus', 'entrega_completa')
            ->where('aprobada_costos', false)
            ->update(['estatus' => 'pendiente_entrega']);

        // entrega_parcial -> pendiente_entrega
        DB::table('costos_facturas')
            ->where('estatus', 'entrega_parcial')
            ->update(['estatus' => 'pendiente_entrega']);

        // Recalculate OC statuses
        $ordenesCompra = DB::table('costos_ordenes_compra')
            ->where('estatus', '!=', 'cancelada')
            ->get(['id']);

        foreach ($ordenesCompra as $oc) {
            $facturas = DB::table('costos_facturas')
                ->where('orden_compra_id', $oc->id)
                ->where('estatus', '!=', 'cancelada')
                ->get(['id', 'estatus']);

            if ($facturas->isEmpty()) {
                DB::table('costos_ordenes_compra')
                    ->where('id', $oc->id)
                    ->update(['estatus' => 'pendiente_factura']);

                continue;
            }

            if ($facturas->every(fn ($f) => $f->estatus === 'pagada')) {
                DB::table('costos_ordenes_compra')
                    ->where('id', $oc->id)
                    ->update(['estatus' => 'pagada']);

                continue;
            }

            if ($facturas->every(fn ($f) => in_array($f->estatus, ['pendiente_pago', 'pagada']))) {
                DB::table('costos_ordenes_compra')
                    ->where('id', $oc->id)
                    ->update(['estatus' => 'pendiente_pago']);

                continue;
            }

            $allHaveEntrega = $facturas->every(function ($f) {
                return DB::table('costos_entregas')
                    ->where('factura_id', $f->id)
                    ->exists();
            });

            if ($allHaveEntrega) {
                DB::table('costos_ordenes_compra')
                    ->where('id', $oc->id)
                    ->update(['estatus' => 'pendiente_aprobacion']);

                continue;
            }

            DB::table('costos_ordenes_compra')
                ->where('id', $oc->id)
                ->update(['estatus' => 'pendiente_entrega']);
        }
    }

    public function down(): void
    {
        // Revertir estatus de facturas a valores pre-migración
        DB::table('costos_facturas')
            ->where('estatus', 'pendiente_pago')
            ->update(['estatus' => 'entrega_completa']);

        DB::table('costos_facturas')
            ->where('estatus', 'pagada')
            ->update(['estatus' => 'entrega_completa']);

        // Revertir estatus de órdenes de compra
        DB::table('costos_ordenes_compra')
            ->where('estatus', 'pendiente_pago')
            ->update(['estatus' => 'pendiente_aprobacion']);

        DB::table('costos_ordenes_compra')
            ->where('estatus', 'pagada')
            ->update(['estatus' => 'pendiente_aprobacion']);

        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->dropForeign(['aceptada_contabilidad_por']);
            $table->dropColumn(['aceptada_contabilidad', 'aceptada_contabilidad_por', 'aceptada_contabilidad_at']);
        });
    }
};
