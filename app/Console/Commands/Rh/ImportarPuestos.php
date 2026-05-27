<?php

namespace App\Console\Commands\Rh;

use App\Services\Rh\Puestos\PuestoExcelParser;
use App\Services\Rh\Puestos\PuestoImporter;
use Illuminate\Console\Command;

class ImportarPuestos extends Command
{
    protected $signature = 'rh:importar-puestos
                            {--path=info : Carpeta a escanear (relativa al root del proyecto)}
                            {--dry-run : No escribe a BD, solo reporta lo que haria}';

    protected $description = 'Importa puestos (con skills/requerimientos/actividades) desde los Excel del descriptivo organizacional.';

    public function handle(PuestoExcelParser $parser, PuestoImporter $importer): int
    {
        $path = base_path((string) $this->option('path'));
        $dryRun = (bool) $this->option('dry-run');

        if (! is_dir($path)) {
            $this->error("Carpeta no existe: {$path}");

            return self::FAILURE;
        }

        $files = array_merge(glob($path.DIRECTORY_SEPARATOR.'*.xlsx') ?: [], glob($path.DIRECTORY_SEPARATOR.'*.XLSX') ?: []);
        if ($files === []) {
            $this->warn("No se encontraron .xlsx en {$path}");

            return self::SUCCESS;
        }

        $this->info(sprintf('Procesando %d archivo(s) desde %s%s', count($files), $path, $dryRun ? ' [DRY-RUN]' : ''));
        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        $importer->resetCaches();
        $totalBloques = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $pendientesJefes = [];

        foreach ($files as $file) {
            try {
                $bloques = $parser->parse($file);
                foreach ($bloques as $bloque) {
                    $totalBloques++;
                    $resultado = $importer->importBloque($bloque, $dryRun);
                    match ($resultado['accion']) {
                        'created' => $created++,
                        'updated' => $updated++,
                        default => $skipped++,
                    };
                    if (! $dryRun && $resultado['puesto_id'] !== null && ! empty($bloque['jefe_inmediato_nombre'])) {
                        $pendientesJefes[] = [
                            'puesto_id' => $resultado['puesto_id'],
                            'jefe_nombre' => (string) $bloque['jefe_inmediato_nombre'],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = basename($file).': '.$e->getMessage();
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $jefesReport = ['matched' => 0, 'unmatched' => []];
        if (! $dryRun && $pendientesJefes !== []) {
            $this->info('Segunda pasada: enlazando jefes inmediatos...');
            $jefesReport = $importer->enlazarJefes($pendientesJefes, $dryRun);
        }

        $this->info('=== RESUMEN ===');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Archivos procesados', count($files)],
                ['Bloques encontrados', $totalBloques],
                ['Puestos creados', $created],
                ['Puestos actualizados', $updated],
                ['Bloques sin nombre (skip)', $skipped],
                ['Jefes inmediatos enlazados', $jefesReport['matched']],
                ['Jefes inmediatos no encontrados', count($jefesReport['unmatched'])],
                ['Errores de parseo', count($errors)],
            ],
        );

        if ($jefesReport['unmatched'] !== []) {
            $this->warn('Jefes no encontrados (mostrando primeros 10):');
            foreach (array_slice($jefesReport['unmatched'], 0, 10) as $j) {
                $this->line('  - '.$j);
            }
        }

        if ($errors !== []) {
            $this->warn('Errores:');
            foreach (array_slice($errors, 0, 10) as $e) {
                $this->line('  - '.$e);
            }
        }

        if ($dryRun) {
            $this->info('[DRY-RUN] No se escribieron cambios.');
        }

        return self::SUCCESS;
    }
}
