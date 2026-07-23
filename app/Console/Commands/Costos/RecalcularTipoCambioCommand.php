<?php

namespace App\Console\Commands\Costos;

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\SolicitudPago;
use App\Services\Costos\AcumuladoLedger;
use App\Services\Costos\TipoCambioService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula en MXN los cargos presupuestales Aplicados de documentos en divisa.
 *
 * Dos modos:
 *  - Objetivo (--solicitud / --requisicion): recalcula solo esos documentos con
 *    el TC guardado en cada uno. Úsalo tras editar el tipo de cambio de un
 *    documento para re-aplicarlo a sus afectaciones.
 *  - Global (sin objetivos): backfill de los cargos que quedaron "en crudo"
 *    (número de la divisa, moneda='mxn' por el default viejo) usando el TC de
 *    hoy. Idempotente: los ya sellados en divisa se ignoran.
 */
class RecalcularTipoCambioCommand extends Command
{
    protected $signature = 'costos:recalcular-tipo-cambio
        {--moneda=usd : Divisa a recalcular en modo global (usd, eur o all)}
        {--solicitud=* : IDs de solicitudes de pago a recalcular con su TC guardado}
        {--requisicion=* : IDs de requisiciones a recalcular con el TC de su(s) OC}
        {--dry-run : Muestra lo que cambiaría sin escribir}';

    protected $description = 'Recalcula en MXN los cargos presupuestales Aplicados de documentos en divisa, por objetivo (con su TC guardado) o global (crudo, con el TC de hoy).';

    public function handle(TipoCambioService $tipoCambio, AcumuladoLedger $ledger): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $solicitudes = array_filter(array_map('intval', (array) $this->option('solicitud')));
        $requisiciones = array_filter(array_map('intval', (array) $this->option('requisicion')));

        if ($solicitudes || $requisiciones) {
            return $this->recalcularObjetivos($solicitudes, $requisiciones, $ledger, $dryRun);
        }

        return $this->recalcularGlobalCrudo($tipoCambio, $ledger, $dryRun);
    }

    /**
     * Modo objetivo: recalcula las afectaciones de documentos concretos usando
     * el TC guardado en cada documento (OC o SolicitudPago).
     *
     * @param  list<int>  $solicitudes
     * @param  list<int>  $requisiciones
     */
    private function recalcularObjetivos(array $solicitudes, array $requisiciones, AcumuladoLedger $ledger, bool $dryRun): int
    {
        /** @var array<string, Model> $entidades */
        $entidades = [];

        foreach (SolicitudPago::query()->whereIn('id', $solicitudes)->get() as $sp) {
            // Una SP originada en OC ejerce presupuesto vía la OC, no la SP.
            $doc = $sp->orden_compra_id ? $sp->ordenCompra : $sp;
            if ($doc) {
                $entidades[$doc::class.':'.$doc->getKey()] = $doc;
            }
        }

        foreach (OrdenCompra::query()->whereIn('requisicion_id', $requisiciones)->get() as $oc) {
            $entidades[$oc::class.':'.$oc->getKey()] = $oc;
        }

        $filas = [];
        $procesados = 0;
        $deltaTotal = 0.0;

        foreach ($entidades as $doc) {
            $moneda = $this->monedaDocumento($doc);
            $tc = (float) ($doc->tipo_cambio ?? 1);

            if ($moneda === null || $moneda === 'mxn') {
                continue;
            }

            $afectados = RubroAfectado::query()
                ->where('entrada_type', $doc::class)
                ->where('entrada_id', $doc->getKey())
                ->where('estatus', RubroAfectadoEstatus::Aplicado->value)
                ->get();

            foreach ($afectados as $ra) {
                $procesados += (int) $this->recalcularCargo($ra, $moneda, $tc, $ledger, $dryRun, $filas, $deltaTotal, (string) $doc->folio);
            }
        }

        return $this->reportar($filas, $procesados, $deltaTotal, $dryRun);
    }

    /**
     * Modo global: backfill de los cargos en crudo con el TC de hoy.
     */
    private function recalcularGlobalCrudo(TipoCambioService $tipoCambio, AcumuladoLedger $ledger, bool $dryRun): int
    {
        $filtro = strtolower((string) $this->option('moneda'));

        $candidatos = RubroAfectado::query()
            ->where('estatus', RubroAfectadoEstatus::Aplicado->value)
            ->where('moneda', 'mxn')
            ->get();

        $filas = [];
        $procesados = 0;
        $deltaTotal = 0.0;

        foreach ($candidatos as $ra) {
            $moneda = $this->monedaDocumento($ra->entrada);

            if ($moneda === null || $moneda === 'mxn' || ($filtro !== 'all' && $moneda !== $filtro)) {
                continue;
            }

            $tc = $tipoCambio->mxnPorUnidad($moneda);
            $procesados += (int) $this->recalcularCargo($ra, $moneda, $tc, $ledger, $dryRun, $filas, $deltaTotal, (string) ($ra->entrada?->folio ?? ''));
        }

        return $this->reportar($filas, $procesados, $deltaTotal, $dryRun);
    }

    /**
     * Recalcula un RubroAfectado: MXN = origen × tc, ajusta el acumulado por el
     * delta y sella moneda/monto_origen/tipo_cambio. Devuelve true si cambió.
     *
     * @param  array<int, array<int, string>>  $filas
     */
    private function recalcularCargo(RubroAfectado $ra, string $moneda, float $tc, AcumuladoLedger $ledger, bool $dryRun, array &$filas, float &$deltaTotal, string $folio): bool
    {
        // Si ya está sellado en la divisa, el origen es monto_origen; si viene en
        // crudo (moneda='mxn'), el propio monto es el número de la divisa.
        $montoOrigen = $ra->monto_origen !== null ? (float) $ra->monto_origen : (float) $ra->monto;
        $montoMxn = round($montoOrigen * $tc, 2);
        $delta = round($montoMxn - (float) $ra->monto, 2);

        if (abs($delta) < 0.005 && $ra->moneda === $moneda) {
            return false;
        }

        $filas[] = [$ra->id, $folio, strtoupper($moneda), number_format($montoOrigen, 2), $tc, number_format($montoMxn, 2), number_format($delta, 2)];
        $deltaTotal += $delta;

        if ($dryRun) {
            return true;
        }

        DB::transaction(function () use ($ra, $moneda, $montoOrigen, $montoMxn, $tc, $delta, $ledger) {
            if (abs($delta) >= 0.005) {
                $ledger->registrarPorId((int) $ra->obra_rubro_id, $delta, $ra->id, "Recálculo TC {$moneda}");
            }
            $ra->update([
                'moneda' => $moneda,
                'monto_origen' => $montoOrigen,
                'tipo_cambio' => $tc,
                'monto' => $montoMxn,
            ]);
        });

        return true;
    }

    /**
     * @param  array<int, array<int, string>>  $filas
     */
    private function reportar(array $filas, int $procesados, float $deltaTotal, bool $dryRun): int
    {
        if ($procesados === 0) {
            $this->info('No hay cargos por recalcular.');

            return self::SUCCESS;
        }

        $this->table(['RubroAfectado', 'Folio', 'Moneda', 'Monto origen', 'TC', 'Monto MXN', 'Delta'], $filas);
        $verbo = $dryRun ? 'Se recalcularían' : 'Recalculados';
        $this->info("{$verbo} {$procesados} cargo(s). Ajuste total al acumulado: ".number_format($deltaTotal, 2).' MXN.');

        if ($dryRun) {
            $this->comment('Modo dry-run: no se escribió nada. Corre sin --dry-run para aplicar.');
        }

        return self::SUCCESS;
    }

    private function monedaDocumento(?Model $entrada): ?string
    {
        return match (true) {
            $entrada instanceof OrdenCompra => strtolower((string) $entrada->moneda),
            $entrada instanceof SolicitudPago => strtolower((string) $entrada->tipo_moneda),
            default => null,
        };
    }
}
