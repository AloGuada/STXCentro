<?php

namespace App\Console\Commands\Cal;

use App\Models\Cal\Reporte;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Llena folios faltantes (NULL) en reportes no-plantilla y, opcionalmente,
 * regenera los duplicados. La fuente de verdad es la columna persistida.
 *
 * Estrategia:
 *  1. Para cada (year, month) con reportes no-plantilla, encuentra el max
 *     folio existente del mes.
 *  2. A partir de ahi, asigna folios nuevos a los que tengan NULL en orden
 *     cronologico (created_at + id desempate).
 *  3. Con --regenerar-duplicados: agrupa por folio, deja el mas viejo y
 *     regenera los demas como si fueran NULL.
 *
 * Es idempotente: correrlo dos veces no cambia nada (los reportes que ya
 * tienen folio se ignoran).
 */
class BackfillFolios extends Command
{
    protected $signature = 'cal:backfill-folios
                            {--dry-run : Solo muestra que cambiaria sin escribir}
                            {--regenerar-duplicados : Regenera folios duplicados al ultimo disponible del mes}';

    protected $description = 'Llena folios NULL en reportes no-plantilla; opcionalmente regenera duplicados.';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $regenerar = $this->option('regenerar-duplicados');

        if ($dry) {
            $this->warn('DRY RUN — sin escribir.');
        }

        $cambios = 0;

        DB::transaction(function () use ($dry, $regenerar, &$cambios) {
            if ($regenerar) {
                $cambios += $this->regenerarDuplicados($dry);
            }

            $cambios += $this->llenarNulls($dry);
        });

        $this->info("Listo. {$cambios} reportes actualizados.");

        return self::SUCCESS;
    }

    private function llenarNulls(bool $dry): int
    {
        $sinFolio = Reporte::where('es_plantilla', false)
            ->whereNull('folio')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($sinFolio->isEmpty()) {
            $this->line('  No hay reportes sin folio.');

            return 0;
        }

        $this->line("  {$sinFolio->count()} reportes sin folio para procesar.");
        $contadores = []; // ['IVYYYYMM' => max_actual]
        $aplicados = 0;

        foreach ($sinFolio as $r) {
            $fecha = $r->created_at ?? Carbon::now();
            $prefix = sprintf('IV%s%s', $fecha->format('Y'), $fecha->format('m'));

            if (! isset($contadores[$prefix])) {
                $ultimoFolio = Reporte::where('folio', 'like', $prefix.'%')
                    ->where('es_plantilla', false)
                    ->orderByDesc('folio')
                    ->value('folio');
                $contadores[$prefix] = $ultimoFolio
                    ? (int) substr($ultimoFolio, strlen($prefix))
                    : 0;
            }

            $contadores[$prefix]++;
            $folio = sprintf('%s%02d', $prefix, $contadores[$prefix]);

            $this->line("  Reporte id={$r->id} -> {$folio}");

            if (! $dry) {
                $r->folio = $folio;
                $r->saveQuietly();
            }
            $aplicados++;
        }

        return $aplicados;
    }

    private function regenerarDuplicados(bool $dry): int
    {
        $duplicados = DB::table('cal_reportes')
            ->select('folio', DB::raw('COUNT(*) as n'))
            ->where('es_plantilla', false)
            ->whereNotNull('folio')
            ->groupBy('folio')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('n', 'folio');

        if ($duplicados->isEmpty()) {
            $this->line('  No hay folios duplicados.');

            return 0;
        }

        $this->line("  {$duplicados->count()} folios duplicados.");
        $aplicados = 0;

        foreach ($duplicados as $folio => $cuantos) {
            // Mantener el mas viejo, anular el resto para que llenarNulls los regenere
            $aRegenerar = Reporte::where('folio', $folio)
                ->where('es_plantilla', false)
                ->orderBy('created_at')
                ->orderBy('id')
                ->skip(1)
                ->take(PHP_INT_MAX)
                ->pluck('id');

            $this->line("  {$folio}: regenerando {$aRegenerar->count()} duplicados");

            if (! $dry) {
                Reporte::whereIn('id', $aRegenerar)->update(['folio' => null]);
            }
            $aplicados += $aRegenerar->count();
        }

        return $aplicados;
    }
}
