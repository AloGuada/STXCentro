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
 * Elimina por completo una obra y todo lo que cuelga de ella en cobranza y
 * producción, conservando el proyecto al que pertenece.
 *
 * SEGURIDAD: se niega a borrar una obra que tenga presupuesto de costos ligado
 * (presupuesto propio, de sus partidas adicionales, o centros de costo). El
 * presupuesto debe deslindarse o eliminarse primero (p.ej. costos:borrar-
 * presupuesto). Así este comando nunca destruye histórico presupuestal.
 *
 * Se borra: cobranza (partidas, estimaciones + pagos/retenciones/historial,
 * anticipos, adendas, comparativos, deducciones, eventos, disputas,
 * penalizaciones, etapas PMO) y producción de la obra (conceptos, registros,
 * grupos de precio). Las OCs, requisiciones y anticipos de proveedor sobreviven
 * con obra_id nulo.
 *
 * Por defecto corre en dry-run (solo reporta). Requiere --force para borrar.
 */
class BorrarObraCommand extends Command
{
    protected $signature = 'cob:borrar-obra
        {id* : ID(s) de la obra a eliminar}
        {--force : Ejecuta el borrado; sin esta bandera solo muestra el dry-run}';

    protected $description = 'Elimina una obra y sus datos de cobranza y producción (solo si no tiene presupuesto ligado), conservando el proyecto';

    public function handle(): int
    {
        $ids = collect($this->argument('id'))->map(fn ($id): int => (int) $id)->unique();

        $obras = Obra::query()->whereIn('id', $ids)->get();

        $faltantes = $ids->diff($obras->pluck('id'));
        if ($faltantes->isNotEmpty()) {
            $this->error('No existe obra con id: '.$faltantes->implode(', '));

            return self::FAILURE;
        }

        $conPresupuesto = $obras->filter(fn (Obra $obra): bool => $this->tienePresupuestoLigado($obra));
        if ($conPresupuesto->isNotEmpty()) {
            $this->error('No se puede borrar: las siguientes obras tienen presupuesto de costos ligado.');
            foreach ($conPresupuesto as $obra) {
                $this->line("  • Obra {$obra->id} ({$obra->no}) — deslinda o elimina su presupuesto primero (costos:borrar-presupuesto).");
            }

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
            // Producción: prod_registros (RESTRICT sobre concepto) antes que conceptos.
            DB::table('prod_registros')->whereIn('concepto_id', $refs['conceptos'])->delete();
            DB::table('conceptos')->whereIn('obra_id', $ids)->delete();
            DB::table('prod_grupos_precio')->whereIn('obra_id', $ids)->delete();

            // Media polimórfica de cobranza (sin FK, no cascadea sola).
            DB::table('media')
                ->where(fn ($q) => $q->where('mediable_type', Anticipo::class)->whereIn('mediable_id', $refs['anticipos']))
                ->orWhere(fn ($q) => $q->where('mediable_type', EstimacionPago::class)->whereIn('mediable_id', $refs['estimacionPagos']))
                ->delete();

            // OC referencia la obra con FK NO ACTION (no se anula sola): se
            // desenlaza para que sobreviva. Requisiciones y anticipos de proveedor
            // usan SET NULL y se anulan solos.
            DB::table('costos_ordenes_compra')->whereIn('obra_id', $ids)->update(['obra_id' => null]);

            // La obra: cascadea todo cobranza.
            Obra::whereIn('id', $ids)->delete();
        });

        $this->newLine();
        $this->info("Eliminadas {$obras->count()} obra(s) y sus datos de cobranza/producción. Proyecto(s) conservado(s).");

        return self::SUCCESS;
    }

    /**
     * True si la obra tiene presupuesto de costos ligado: presupuesto propio, de
     * alguna de sus partidas adicionales, o algún centro de costo (obra_rubro).
     */
    private function tienePresupuestoLigado(Obra $obra): bool
    {
        $partidaIds = DB::table('cob_partidas')->where('obra_id', $obra->id)->pluck('id');

        $presupuestos = DB::table('costos_presupuestos')
            ->where(fn ($q) => $q->where('presupuestable_type', Obra::class)->where('presupuestable_id', $obra->id))
            ->orWhere(fn ($q) => $q->where('presupuestable_type', Partida::class)->whereIn('presupuestable_id', $partidaIds))
            ->exists();

        $rubros = DB::table('costos_obra_rubros')->where('obra_id', $obra->id)->exists();

        return $presupuestos || $rubros;
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

        return [
            'conceptos' => $conceptos,
            'partidas' => $partidas,
            'estimaciones' => $estimaciones,
            'estimacionPagos' => $estimacionPagos,
            'anticipos' => $anticipos,
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
