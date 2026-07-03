<?php

namespace App\Console\Commands\Costos;

use App\Models\Costos\Aprobacion;
use App\Models\Costos\Cancelacion;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Pago;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\SolicitudPago;
use App\Models\Media;
use App\Services\Costos\ApartadoPresupuestal;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Elimina por completo una requisición de Costos y TODO lo que cuelga de ella:
 * sus órdenes de compra generadas con su downstream (facturas, pagos, entregas,
 * solicitudes de pago, notas de crédito, devoluciones...), las tablas
 * polimórficas sin FK (media, cancelaciones, rubros afectados, aprobaciones,
 * activity log) y revierte el presupuesto apartado/afectado.
 *
 * Por defecto corre en modo dry-run (solo reporta). Requiere --force para borrar.
 */
class BorrarRequisicionCommand extends Command
{
    protected $signature = 'costos:borrar-requisicion
        {folio : Folio de la requisición a eliminar}
        {--force : Ejecuta el borrado; sin esta bandera solo muestra el dry-run}';

    protected $description = 'Elimina una requisición de Costos y todo lo relacionado (OCs, facturas, pagos, entregas, presupuesto) por folio';

    public function handle(ApartadoPresupuestal $apartado): int
    {
        $folio = (string) $this->argument('folio');

        $requisicion = Requisicion::query()->where('folio', $folio)->first();

        if ($requisicion === null) {
            $this->error("No existe ninguna requisición con folio '{$folio}'.");

            return self::FAILURE;
        }

        $ids = $this->recolectarIds($requisicion);
        $plan = $this->construirPlan($requisicion, $ids);

        $estatus = $requisicion->estatus?->value ?? (string) $requisicion->estatus;
        $this->info("Requisición {$requisicion->folio} (id {$requisicion->id}, estatus {$estatus})");
        $this->newLine();
        $this->table(
            ['Tabla / entidad', 'Registros'],
            collect($plan)->map(fn (int $n, string $t): array => [$t, (string) $n])->values()->all(),
        );

        $total = array_sum($plan);

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn("DRY-RUN: no se borró nada. Se eliminarían {$total} registros.");
            $this->line('Vuelve a ejecutar con --force para borrar definitivamente.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($requisicion, $ids, $apartado): void {
            $this->revertirPresupuesto($requisicion, $ids, $apartado);
            $this->borrarPolimorficos($requisicion, $ids);

            // Hijos con FK a nivel BD: el mass-delete respeta los onDelete CASCADE
            // (factura_detalle, notas_credito, entrega_detalle, devoluciones,
            // complementos_pago, detalles/archivos/aprobaciones de solicitud...)
            // y evita disparar model events (recalcular estatus, LogsActivity).
            Pago::whereIn('id', $ids['pagos'])->delete();
            Factura::whereIn('id', $ids['facturas'])->delete();
            Entrega::whereIn('id', $ids['entregas'])->delete();
            SolicitudPago::whereIn('id', $ids['solicitudes'])->delete();

            OrdenCompraDetalle::whereIn('orden_compra_id', $ids['ocs'])->delete();
            OrdenCompra::whereIn('id', $ids['ocs'])->delete();

            // Cascadea detalle -> cotizaciones/selecciones y requisicion_ocs.
            Requisicion::whereKey($requisicion->id)->delete();
        });

        $this->newLine();
        $this->info("Requisición {$folio} y {$total} registros relacionados eliminados.");

        return self::SUCCESS;
    }

    /**
     * IDs de todos los registros del árbol, para contar y borrar sin re-consultar.
     *
     * @return array<string, Collection<int, int>>
     */
    private function recolectarIds(Requisicion $requisicion): array
    {
        $detalles = $requisicion->detalles()->pluck('id');
        $ocs = $requisicion->ordenesGeneradas()->pluck('id');
        $facturas = Factura::query()->whereIn('orden_compra_id', $ocs)->pluck('id');
        $entregas = Entrega::query()->whereIn('orden_compra_id', $ocs)->pluck('id');
        $entregaDetalles = EntregaDetalle::query()->whereIn('entrega_id', $entregas)->pluck('id');
        $solicitudes = SolicitudPago::query()->whereIn('orden_compra_id', $ocs)->pluck('id');
        $ocDetalles = OrdenCompraDetalle::query()->whereIn('orden_compra_id', $ocs)->pluck('id');

        $pagosRaiz = Pago::query()
            ->where(function ($q) use ($facturas, $solicitudes): void {
                $q->where(fn ($w) => $w->where('pagable_type', Factura::class)->whereIn('pagable_id', $facturas))
                    ->orWhere(fn ($w) => $w->where('pagable_type', SolicitudPago::class)->whereIn('pagable_id', $solicitudes));
            })
            ->pluck('id');
        $pagosParciales = Pago::query()->whereIn('pago_padre_id', $pagosRaiz)->pluck('id');

        return [
            'detalles' => $detalles,
            'ocs' => $ocs,
            'facturas' => $facturas,
            'entregas' => $entregas,
            'entregaDetalles' => $entregaDetalles,
            'solicitudes' => $solicitudes,
            'ocDetalles' => $ocDetalles,
            'pagos' => $pagosRaiz->merge($pagosParciales)->unique()->values(),
        ];
    }

    /**
     * Conteo por tabla para el reporte.
     *
     * @param  array<string, Collection<int, int>>  $ids
     * @return array<string, int>
     */
    private function construirPlan(Requisicion $requisicion, array $ids): array
    {
        return array_filter([
            'Requisición' => 1,
            'Detalles requisición' => $ids['detalles']->count(),
            'Cotizaciones' => DB::table('costos_requisicion_cotizacion_precio')->whereIn('requisicion_detalle_id', $ids['detalles'])->count(),
            'Selecciones' => DB::table('costos_requisicion_seleccion')->whereIn('requisicion_detalle_id', $ids['detalles'])->count(),
            'OCs planeadas (requisicion_ocs)' => $requisicion->ocs()->count(),
            'Órdenes de compra' => $ids['ocs']->count(),
            'Detalles OC' => $ids['ocDetalles']->count(),
            'Facturas' => $ids['facturas']->count(),
            'Detalles factura' => DB::table('costos_factura_detalle')->whereIn('factura_id', $ids['facturas'])->count(),
            'Notas de crédito' => DB::table('costos_notas_credito')->whereIn('factura_id', $ids['facturas'])->count(),
            'Aplicaciones de anticipo' => DB::table('costos_anticipo_aplicaciones')->whereIn('factura_id', $ids['facturas'])->count(),
            'Complementos de pago' => DB::table('costos_complementos_pago')->whereIn('factura_id', $ids['facturas'])->count(),
            'Entregas' => $ids['entregas']->count(),
            'Detalles entrega' => $ids['entregaDetalles']->count(),
            'Devoluciones' => DB::table('costos_devoluciones')->whereIn('entrega_detalle_id', $ids['entregaDetalles'])->count(),
            'Solicitudes de pago' => $ids['solicitudes']->count(),
            'Detalles solicitud' => DB::table('costos_solicitudes_pago_detalle')->whereIn('solicitud_id', $ids['solicitudes'])->count(),
            'Archivos solicitud' => DB::table('costos_solicitud_archivos')->whereIn('solicitud_id', $ids['solicitudes'])->count(),
            'Pagos' => $ids['pagos']->count(),
            'Aprobaciones' => $this->contarPorMorfo(Aprobacion::query(), 'aprobable', [
                [Requisicion::class, collect([$requisicion->id])],
                [SolicitudPago::class, $ids['solicitudes']],
            ]),
            'Rubros afectados' => $this->contarPorMorfo(RubroAfectado::query(), 'entrada', [
                [Requisicion::class, collect([$requisicion->id])],
                [OrdenCompra::class, $ids['ocs']],
                [SolicitudPago::class, $ids['solicitudes']],
            ]),
            'Cancelaciones' => $this->contarPorMorfo(Cancelacion::query(), 'cancelable', $this->duenosCancelables($requisicion, $ids)),
            'Media (adjuntos)' => $this->contarPorMorfo(Media::query(), 'mediable', $this->duenosMedia($requisicion, $ids)),
            'Activity log' => $this->contarPorMorfo(Activity::query(), 'subject', $this->duenosActividad($requisicion, $ids)),
        ]);
    }

    /**
     * Revierte el presupuesto apartado/afectado de la requisición, sus OCs y sus
     * solicitudes de pago (decrementa `costos_obra_rubros.acumulado`). Solo toca
     * afectaciones vigentes, así que es idempotente frente a cancelaciones previas.
     *
     * @param  array<string, Collection<int, int>>  $ids
     */
    private function revertirPresupuesto(Requisicion $requisicion, array $ids, ApartadoPresupuestal $apartado): void
    {
        OrdenCompra::query()->whereIn('id', $ids['ocs'])->get()
            ->each(fn (OrdenCompra $oc) => $apartado->cancelarApartadosDe($oc, 'OC borrada por comando'));

        SolicitudPago::query()->whereIn('id', $ids['solicitudes'])->get()
            ->each(fn (SolicitudPago $sp) => $apartado->cancelarApartadosDe($sp, 'solicitud borrada por comando'));

        $apartado->cancelarApartadosDe($requisicion, 'requisición borrada por comando');
    }

    /**
     * Borra las tablas polimórficas (sin FK, nunca cascadean solas). Los rubros
     * afectados ya fueron revertidos en {@see revertirPresupuesto}.
     *
     * @param  array<string, Collection<int, int>>  $ids
     */
    private function borrarPolimorficos(Requisicion $requisicion, array $ids): void
    {
        $this->borrarPorMorfo(Aprobacion::query(), 'aprobable', [
            [Requisicion::class, collect([$requisicion->id])],
            [SolicitudPago::class, $ids['solicitudes']],
        ]);
        $this->borrarPorMorfo(RubroAfectado::query(), 'entrada', [
            [Requisicion::class, collect([$requisicion->id])],
            [OrdenCompra::class, $ids['ocs']],
            [SolicitudPago::class, $ids['solicitudes']],
        ]);
        $this->borrarPorMorfo(Cancelacion::query(), 'cancelable', $this->duenosCancelables($requisicion, $ids));
        $this->borrarPorMorfo(Media::query(), 'mediable', $this->duenosMedia($requisicion, $ids));
        $this->borrarPorMorfo(Activity::query(), 'subject', $this->duenosActividad($requisicion, $ids));
    }

    /**
     * @param  array<string, Collection<int, int>>  $ids
     * @return list<array{0: class-string, 1: Collection<int, int>}>
     */
    private function duenosCancelables(Requisicion $requisicion, array $ids): array
    {
        return [
            [Requisicion::class, collect([$requisicion->id])],
            [OrdenCompra::class, $ids['ocs']],
            [Factura::class, $ids['facturas']],
            [SolicitudPago::class, $ids['solicitudes']],
            [Pago::class, $ids['pagos']],
        ];
    }

    /**
     * @param  array<string, Collection<int, int>>  $ids
     * @return list<array{0: class-string, 1: Collection<int, int>}>
     */
    private function duenosMedia(Requisicion $requisicion, array $ids): array
    {
        return [
            [Requisicion::class, collect([$requisicion->id])],
            [OrdenCompra::class, $ids['ocs']],
            [Factura::class, $ids['facturas']],
            [Entrega::class, $ids['entregas']],
            [SolicitudPago::class, $ids['solicitudes']],
            [Pago::class, $ids['pagos']],
        ];
    }

    /**
     * @param  array<string, Collection<int, int>>  $ids
     * @return list<array{0: class-string, 1: Collection<int, int>}>
     */
    private function duenosActividad(Requisicion $requisicion, array $ids): array
    {
        return [
            [Requisicion::class, collect([$requisicion->id])],
            [OrdenCompra::class, $ids['ocs']],
            [Factura::class, $ids['facturas']],
            [SolicitudPago::class, $ids['solicitudes']],
            [Pago::class, $ids['pagos']],
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @param  list<array{0: class-string, 1: Collection<int, int>}>  $duenos
     */
    private function contarPorMorfo($query, string $morfo, array $duenos): int
    {
        return (clone $query)->where(function ($q) use ($morfo, $duenos): void {
            foreach ($duenos as [$tipo, $ids]) {
                $q->orWhere(fn ($w) => $w->where("{$morfo}_type", $tipo)->whereIn("{$morfo}_id", $ids));
            }
        })->count();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @param  list<array{0: class-string, 1: Collection<int, int>}>  $duenos
     */
    private function borrarPorMorfo($query, string $morfo, array $duenos): void
    {
        foreach ($duenos as [$tipo, $ids]) {
            if ($ids->isEmpty()) {
                continue;
            }

            (clone $query)->where("{$morfo}_type", $tipo)->whereIn("{$morfo}_id", $ids)->delete();
        }
    }
}
