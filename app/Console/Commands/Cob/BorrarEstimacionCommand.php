<?php

namespace App\Console\Commands\Cob;

use App\Models\Cob\Estimacion;
use App\Models\Cob\EstimacionEstadoHistorial;
use App\Models\Cob\EstimacionPago;
use App\Models\Cob\Retencion;
use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Elimina por completo una estimación de Cobranza y TODO lo que cuelga de ella:
 * sus pagos (con los comprobantes/media que adjuntan), retenciones, historial de
 * estados y las partidas ligadas (pivote). Los hijos con FK cascadean solos, pero
 * la media de los pagos es polimórfica (sin FK) y se borra de forma explícita.
 *
 * Por defecto corre en modo dry-run (solo reporta). Requiere --force para borrar.
 */
class BorrarEstimacionCommand extends Command
{
    protected $signature = 'cob:borrar-estimacion
        {identificador : Folio (o id) de la estimación a eliminar}
        {--force : Ejecuta el borrado; sin esta bandera solo muestra el dry-run}';

    protected $description = 'Elimina una estimación de Cobranza y todo lo relacionado (pagos, comprobantes, retenciones, historial, partidas) por folio o id';

    public function handle(): int
    {
        $identificador = (string) $this->argument('identificador');

        $estimacion = Estimacion::query()->where('folio', $identificador)->first()
            ?? (ctype_digit($identificador) ? Estimacion::query()->find((int) $identificador) : null);

        if ($estimacion === null) {
            $this->error("No existe ninguna estimación con folio o id '{$identificador}'.");

            return self::FAILURE;
        }

        $pagoIds = EstimacionPago::query()->where('estimacion_id', $estimacion->id)->pluck('id');

        $plan = array_filter([
            'Estimación' => 1,
            'Pagos' => $pagoIds->count(),
            'Comprobantes de pago (media)' => Media::query()
                ->where('mediable_type', EstimacionPago::class)
                ->whereIn('mediable_id', $pagoIds)
                ->count(),
            'Retenciones' => Retencion::query()->where('estimacion_id', $estimacion->id)->count(),
            'Historial de estados' => EstimacionEstadoHistorial::query()->where('estimacion_id', $estimacion->id)->count(),
            'Partidas ligadas (pivote)' => DB::table('cob_estimacion_partida')->where('estimacion_id', $estimacion->id)->count(),
        ]);

        $folio = $estimacion->folio ?? "#{$estimacion->id}";
        $this->info("Estimación {$folio} (id {$estimacion->id}, estado {$estimacion->estado}, obra_id {$estimacion->obra_id})");
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

        DB::transaction(function () use ($estimacion, $pagoIds): void {
            // Media de los pagos: polimórfica, sin FK, no cascadea.
            Media::query()
                ->where('mediable_type', EstimacionPago::class)
                ->whereIn('mediable_id', $pagoIds)
                ->delete();

            // Hijos con FK (cascadearían al borrar la estimación, pero los
            // borramos explícitamente para no depender del enforcement de FKs).
            EstimacionPago::query()->where('estimacion_id', $estimacion->id)->delete();
            Retencion::query()->where('estimacion_id', $estimacion->id)->delete();
            EstimacionEstadoHistorial::query()->where('estimacion_id', $estimacion->id)->delete();
            DB::table('cob_estimacion_partida')->where('estimacion_id', $estimacion->id)->delete();

            Estimacion::query()->whereKey($estimacion->id)->delete();
        });

        $this->newLine();
        $this->info("Estimación {$folio} y {$total} registros relacionados eliminados.");

        return self::SUCCESS;
    }
}
