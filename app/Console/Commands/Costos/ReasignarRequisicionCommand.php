<?php

namespace App\Console\Commands\Costos;

use App\Models\Costos\Requisicion;
use App\Services\Costos\ReasignacionCentroCostosRequisicion;
use Illuminate\Console\Command;

/**
 * Cambia los centros de costos de una requisición y de todo lo que heredó de
 * ella (órdenes de compra, solicitudes de pago de contado y sus cargos al
 * presupuesto) con un mapa `obra_rubro` viejo → nuevo por id.
 *
 * Por defecto sólo muestra el plan. Requiere --force para ejecutar. La lógica
 * vive en {@see ReasignacionCentroCostosRequisicion}.
 */
class ReasignarRequisicionCommand extends Command
{
    protected $signature = 'costos:reasignar-requisicion
        {folio : Folio de la requisición}
        {--mapa=* : Centro de costos viejo:nuevo por id de obra_rubro (ej. --mapa=12:34); se puede repetir}
        {--motivo= : Por qué se reasigna; queda en la bitácora y en cada cargo movido}
        {--force : Ejecuta la reasignación; sin esta bandera sólo muestra el plan}';

    protected $description = 'Reasigna los centros de costos de una requisición, sus OC y solicitudes de pago, moviendo sus cargos al presupuesto';

    public function handle(ReasignacionCentroCostosRequisicion $reasignacion): int
    {
        $folio = (string) $this->argument('folio');
        $requisicion = Requisicion::query()->where('folio', $folio)->first();

        if ($requisicion === null) {
            $this->error("No existe ninguna requisición con folio '{$folio}'.");

            return self::FAILURE;
        }

        $mapa = $this->mapa();

        if ($mapa === null) {
            return self::INVALID;
        }

        $plan = $reasignacion->planear($requisicion, $mapa);

        $estatus = $requisicion->estatus?->value ?? (string) $requisicion->estatus;
        $this->info("Requisición {$requisicion->folio} (id {$requisicion->id}, estatus {$estatus})");

        if ($plan['errores'] !== []) {
            foreach ($plan['errores'] as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Partidas que cambian de centro de costos:');
        $this->table(
            ['Documento', 'Partida', 'De', 'A'],
            array_map(fn (array $p): array => [$p['documento'], $p['partida'], $p['de'], $p['a']], $plan['partidas']),
        );

        $this->line('Cargos al presupuesto que se mueven:');
        $this->table(
            ['Documento', 'Estatus', 'Monto MXN', 'De', 'A'],
            array_map(fn (array $c): array => [$c['documento'], $c['estatus'], number_format($c['monto'], 2), $c['de'], $c['a']], $plan['cargos']),
        );

        if ($plan['ocs_omitidas'] !== []) {
            $this->line('OC canceladas que no se tocan: '.implode(', ', $plan['ocs_omitidas']));
        }

        foreach ($plan['avisos'] as $aviso) {
            $this->warn($aviso);
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('DRY-RUN: no se cambió nada.');
            $this->line('Vuelve a ejecutar con --force y --motivo para reasignar.');

            return self::SUCCESS;
        }

        $motivo = trim((string) $this->option('motivo'));

        if (mb_strlen($motivo) < 10) {
            $this->error('Indica --motivo (al menos 10 caracteres): queda en la bitácora y en cada cargo movido.');

            return self::INVALID;
        }

        $hecho = $reasignacion->reasignar($requisicion, $mapa, "Reasignación por comando: {$motivo}");

        $this->newLine();
        $this->info("Listo: {$hecho['partidas']} partidas y {$hecho['cargos']} cargos reasignados en {$requisicion->folio}.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>|null nulo si alguna entrada no tiene la forma viejo:nuevo
     */
    private function mapa(): ?array
    {
        $mapa = [];

        foreach ((array) $this->option('mapa') as $par) {
            if (! preg_match('/^\s*(\d+)\s*:\s*(\d+)\s*$/', (string) $par, $m)) {
                $this->error("--mapa='{$par}' no tiene la forma viejo:nuevo con ids de obra_rubro (ej. --mapa=12:34).");

                return null;
            }

            if (array_key_exists((int) $m[1], $mapa)) {
                $this->error("El centro de costos {$m[1]} aparece dos veces en --mapa.");

                return null;
            }

            $mapa[(int) $m[1]] = (int) $m[2];
        }

        return $mapa;
    }
}
