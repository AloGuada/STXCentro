<?php

namespace App\Console\Commands\Cob;

use App\Models\Cob\Anticipo;
use App\Models\Cob\EstimacionPago;
use App\Models\Cob\Partida;
use App\Models\Obra;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Elimina por completo una obra y TODO lo que cuelga de ella, conservando el
 * proyecto al que pertenece.
 *
 * Se borra: cobranza (partidas, estimaciones + pagos/retenciones/historial,
 * anticipos, adendas, comparativos, deducciones, eventos, disputas,
 * penalizaciones, etapas PMO), producción de la obra (conceptos, registros,
 * grupos de precio) y el presupuesto de costos (rubros, afectaciones,
 * movimientos), incluidos los presupuestos de las partidas adicionales.
 *
 * SOBREVIVEN (se les anula obra_id): órdenes de compra, requisiciones y
 * anticipos de proveedor de costos — junto con todo su downstream. La
 * facturación/pagos de contado no se destruye al borrar la obra.
 *
 * Por defecto corre en dry-run (solo reporta). Requiere --force para borrar.
 */
class BorrarObraCommand extends Command
{
    protected $signature = 'cob:borrar-obra
        {id* : ID(s) de la obra a eliminar}
        {--force : Ejecuta el borrado; sin esta bandera solo muestra el dry-run}';

    protected $description = 'Elimina una obra y todo lo relacionado (cobranza, producción, presupuesto), conservando el proyecto';

    public function handle(): int
    {
        $ids = collect($this->argument('id'))->map(fn ($id): int => (int) $id)->unique();

        $obras = Obra::query()->whereIn('id', $ids)->get();

        $faltantes = $ids->diff($obras->pluck('id'));
        if ($faltantes->isNotEmpty()) {
            $this->error('No existe obra con id: '.$faltantes->implode(', '));

            return self::FAILURE;
        }

        $refs = $this->recolectarIds($ids);
        $plan = $this->construirPlan($ids, $refs);

        foreach ($obras as $obra) {
            $this->line("• Obra {$obra->id}: {$obra->no} — {$obra->descripcion} (proyecto {$obra->proyecto_id} se conserva)");
        }
        $this->newLine();
        $this->table(
            ['Tabla / entidad', 'Registros'],
            collect($plan)->map(fn (int $n, string $t): array => [$t, (string) $n])->values()->all(),
        );

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('DRY-RUN: no se borró nada. Vuelve a ejecutar con --force para borrar definitivamente.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($ids, $refs): void {
            // Bloqueadores RESTRICT, en orden (producción → costos).
            DB::table('prod_registros')->whereIn('concepto_id', $refs['conceptos'])->delete();
            DB::table('conceptos')->whereIn('obra_id', $ids)->delete();
            DB::table('prod_grupos_precio')->whereIn('obra_id', $ids)->delete();
            DB::table('costos_rubros_afectados')->whereIn('obra_rubro_id', $refs['obraRubros'])->delete();

            // Polimórficos sin FK (no cascadean solos).
            DB::table('media')
                ->where(fn ($q) => $q->where('mediable_type', Anticipo::class)->whereIn('mediable_id', $refs['anticipos']))
                ->orWhere(fn ($q) => $q->where('mediable_type', EstimacionPago::class)->whereIn('mediable_id', $refs['estimacionPagos']))
                ->delete();

            // Presupuestos de costos (obra + partidas adicionales); sus obra_rubros
            // cascadean por presupuesto_id.
            DB::table('costos_presupuestos')->whereIn('id', $refs['presupuestos'])->delete();

            // La OC referencia la obra con FK NO ACTION (no se anula sola): se
            // desenlaza a mano para que sobreviva. Requisiciones y anticipos de
            // proveedor usan SET NULL y se anulan solos al borrar la obra.
            DB::table('costos_ordenes_compra')->whereIn('obra_id', $ids)->update(['obra_id' => null]);

            // La obra: cascadea todo cobranza y el presupuesto restante.
            Obra::whereIn('id', $ids)->delete();
        });

        $this->newLine();
        $this->info("Eliminadas {$obras->count()} obra(s) y todo lo relacionado. Proyecto(s) conservado(s).");

        return self::SUCCESS;
    }

    /**
     * IDs de los sub-árboles que necesitan borrado o conteo explícito.
     *
     * @param  Collection<int, int>  $ids
     * @return array<string, Collection<int, int>>
     */
    private function recolectarIds(Collection $ids): array
    {
        $conceptos = DB::table('conceptos')->whereIn('obra_id', $ids)->pluck('id');
        $partidas = DB::table('cob_partidas')->whereIn('obra_id', $ids)->pluck('id');
        $estimaciones = DB::table('cob_estimaciones')->whereIn('obra_id', $ids)->pluck('id');
        $estimacionPagos = DB::table('cob_estimaciones_pagos')->whereIn('estimacion_id', $estimaciones)->pluck('id');
        $anticipos = DB::table('cob_anticipos')->whereIn('obra_id', $ids)->pluck('id');

        $presupuestos = DB::table('costos_presupuestos')
            ->where(fn ($q) => $q->where('presupuestable_type', Obra::class)->whereIn('presupuestable_id', $ids))
            ->orWhere(fn ($q) => $q->where('presupuestable_type', Partida::class)->whereIn('presupuestable_id', $partidas))
            ->pluck('id');

        $obraRubros = DB::table('costos_obra_rubros')
            ->whereIn('obra_id', $ids)
            ->orWhereIn('presupuesto_id', $presupuestos)
            ->pluck('id');

        return [
            'conceptos' => $conceptos,
            'partidas' => $partidas,
            'estimaciones' => $estimaciones,
            'estimacionPagos' => $estimacionPagos,
            'anticipos' => $anticipos,
            'presupuestos' => $presupuestos,
            'obraRubros' => $obraRubros,
        ];
    }

    /**
     * Conteo por tabla para el reporte.
     *
     * @param  Collection<int, int>  $ids
     * @param  array<string, Collection<int, int>>  $refs
     * @return array<string, int>
     */
    private function construirPlan(Collection $ids, array $refs): array
    {
        return array_filter([
            'Obras' => $ids->count(),
            // Producción
            'Conceptos' => $refs['conceptos']->count(),
            'Registros de producción' => DB::table('prod_registros')->whereIn('concepto_id', $refs['conceptos'])->count(),
            'Grupos de precio' => DB::table('prod_grupos_precio')->whereIn('obra_id', $ids)->count(),
            // Cobranza
            'Partidas' => $refs['partidas']->count(),
            'Estimaciones' => $refs['estimaciones']->count(),
            'Pagos de estimación' => $refs['estimacionPagos']->count(),
            'Retenciones' => DB::table('cob_retenciones')->whereIn('estimacion_id', $refs['estimaciones'])->count(),
            'Anticipos (cobranza)' => $refs['anticipos']->count(),
            'Adendas' => DB::table('cob_adendas')->whereIn('obra_id', $ids)->count(),
            'Comparativos' => DB::table('cob_comparativos')->whereIn('obra_id', $ids)->count(),
            'Deducciones' => DB::table('cob_deducciones')->whereIn('obra_id', $ids)->count(),
            'Eventos' => DB::table('cob_eventos')->whereIn('obra_id', $ids)->count(),
            'Disputas' => DB::table('cob_disputas')->whereIn('obra_id', $ids)->count(),
            'Penalizaciones' => DB::table('cob_penalizaciones')->whereIn('obra_id', $ids)->count(),
            'Etapas PMO' => DB::table('cob_obra_etapas')->whereIn('obra_id', $ids)->count(),
            // Costos (presupuesto)
            'Presupuestos de costos' => $refs['presupuestos']->count(),
            'Centros de costos (obra_rubros)' => $refs['obraRubros']->count(),
            'Rubros afectados' => DB::table('costos_rubros_afectados')->whereIn('obra_rubro_id', $refs['obraRubros'])->count(),
            'Movimientos de acumulado' => DB::table('costos_rubro_movimientos')->whereIn('obra_rubro_id', $refs['obraRubros'])->count(),
            'Media huérfana limpiada' => DB::table('media')
                ->where(fn ($q) => $q->where('mediable_type', Anticipo::class)->whereIn('mediable_id', $refs['anticipos']))
                ->orWhere(fn ($q) => $q->where('mediable_type', EstimacionPago::class)->whereIn('mediable_id', $refs['estimacionPagos']))
                ->count(),
            // Sobrevivientes (se desenlazan, NO se borran)
            'OC de costos (obra_id → null, sobreviven)' => DB::table('costos_ordenes_compra')->whereIn('obra_id', $ids)->count(),
            'Requisiciones (obra_id → null, sobreviven)' => DB::table('costos_requisiciones')->whereIn('obra_id', $ids)->count(),
            'Anticipos proveedor (obra_id → null, sobreviven)' => DB::table('costos_anticipos')->whereIn('obra_id', $ids)->count(),
        ], fn (int $n): bool => $n > 0);
    }
}
