<?php

namespace App\Console\Commands\Rh;

use App\Models\Rh\OnboardingTareaPlantilla;
use App\Models\Rh\Puesto;
use App\Services\Rh\Onboarding\CronogramaExcelParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarOnboarding extends Command
{
    protected $signature = 'rh:importar-onboarding
                            {--path=info/onboarding_plantillas : Carpeta a escanear (relativa al root del proyecto)}
                            {--dry-run : No escribe a BD, solo reporta lo que haria}';

    protected $description = 'Importa las plantillas de onboarding (tareas por puesto) desde los Excel "Cronograma_Onboarding_*.xlsx".';

    public function handle(CronogramaExcelParser $parser): int
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

        $puestosPorClave = $this->mapaPuestos();

        $this->info(sprintf('Procesando %d archivo(s) desde %s%s', count($files), $path, $dryRun ? ' [DRY-RUN]' : ''));
        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        $matched = 0;
        $totalTareas = 0;
        $enlazados = [];
        $sinDatos = [];
        $sinPuesto = [];
        $errors = [];

        foreach ($files as $file) {
            try {
                $cronograma = $parser->parse($file);
                if ($cronograma === null || $cronograma['puesto_nombre'] === null) {
                    $sinDatos[] = basename($file);
                    $bar->advance();

                    continue;
                }

                $clave = $this->clavePuesto($cronograma['puesto_nombre']);
                $puesto = $puestosPorClave[$clave] ?? null;
                if ($puesto === null) {
                    $sinPuesto[] = sprintf('%s (puesto: "%s")', basename($file), $cronograma['puesto_nombre']);
                    $bar->advance();

                    continue;
                }

                $matched++;
                $totalTareas += count($cronograma['tareas']);
                $enlazados[] = sprintf('%s  ->  %s (%d tareas)', basename($file), $puesto->nombre, count($cronograma['tareas']));

                if (! $dryRun) {
                    $this->sincronizarPlantillas($puesto, $cronograma['tareas']);
                }
            } catch (\Throwable $e) {
                $errors[] = basename($file).': '.$e->getMessage();
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('=== RESUMEN ===');
        $this->table(['Métrica', 'Valor'], [
            ['Archivos procesados', count($files)],
            ['Cronogramas con puesto enlazado', $matched],
            ['Tareas de plantilla importadas', $totalTareas],
            ['Archivos sin datos (skip)', count($sinDatos)],
            ['Cronogramas sin puesto en catálogo', count($sinPuesto)],
            ['Errores de parseo', count($errors)],
        ]);

        if ($enlazados !== []) {
            sort($enlazados);
            $this->info('Enlaces archivo -> puesto:');
            foreach ($enlazados as $e) {
                $this->line('  - '.$e);
            }
        }

        foreach ([['Sin puesto en catálogo', $sinPuesto], ['Sin datos', $sinDatos], ['Errores', $errors]] as [$titulo, $items]) {
            if ($items !== []) {
                $this->warn("{$titulo} (primeros 50):");
                foreach (array_slice($items, 0, 50) as $i) {
                    $this->line('  - '.$i);
                }
            }
        }

        if ($dryRun) {
            $this->info('[DRY-RUN] No se escribieron cambios.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, Puesto> clavePuesto(nombre) => Puesto
     */
    private function mapaPuestos(): array
    {
        $mapa = [];
        foreach (Puesto::query()->get(['id', 'nombre']) as $puesto) {
            $mapa[$this->clavePuesto($puesto->nombre)] = $puesto;
        }

        return $mapa;
    }

    /**
     * Clave de matching tolerante: uppercase sin tildes, expande abreviaturas
     * (AUX.->AUXILIAR, SUP.->SUPERVISOR...), corrige typos comunes, descarta
     * palabras de relleno (DE/DEL/LA) y ordena tokens para ser orden-insensible.
     */
    private function clavePuesto(string $nombre): string
    {
        $s = mb_strtoupper(trim($nombre));
        if (class_exists(\Normalizer::class)) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
            $s = (string) preg_replace('/\p{Mn}+/u', '', $s);
        }
        $s = (string) preg_replace('/[().,;:]/', ' ', $s);

        $expandir = [
            'AUX' => 'AUXILIAR',
            'SUP' => 'SUPERVISOR',
            'COORD' => 'COORDINADOR',
            'ENC' => 'ENCARGADO',
            'ING' => 'INGENIERO',
            'ADMVO' => 'ADMINISTRATIVO',
            'ADMIN' => 'ADMINISTRATIVO',
            'ALMANCEN' => 'ALMACEN',
        ];
        $relleno = ['DE', 'DEL', 'LA', 'EL', 'LOS', 'LAS'];

        $tokens = [];
        foreach (preg_split('/\s+/', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $t) {
            $t = $expandir[$t] ?? $t;
            if (in_array($t, $relleno, true)) {
                continue;
            }
            $tokens[] = $t;
        }
        sort($tokens);

        return implode(' ', $tokens);
    }

    /**
     * Reemplaza idempotentemente las tareas de plantilla del puesto.
     *
     * @param  list<array<string, mixed>>  $tareas
     */
    private function sincronizarPlantillas(Puesto $puesto, array $tareas): void
    {
        DB::transaction(function () use ($puesto, $tareas) {
            OnboardingTareaPlantilla::query()->where('puesto_id', $puesto->id)->delete();
            foreach ($tareas as $t) {
                OnboardingTareaPlantilla::create([
                    'puesto_id' => $puesto->id,
                    'titulo' => $t['titulo'],
                    'descripcion' => null,
                    'etapa' => $t['etapa'],
                    'responsable' => $t['responsable'],
                    'duracion_estimada' => $t['duracion_estimada'],
                    'dias_desde_inicio' => $t['dias_desde_inicio'],
                    'orden' => $t['orden'],
                ]);
            }
        });
    }
}
