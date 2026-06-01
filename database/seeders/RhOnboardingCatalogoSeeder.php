<?php

namespace Database\Seeders;

use App\Models\Rh\OnboardingTareaPlantilla;
use App\Models\Rh\Puesto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Siembra las plantillas de onboarding (tareas por puesto) desde el snapshot JSON.
 * Cada bloque queda enlazado a su puesto por nombre (case/acento-insensible).
 * Requiere que los puestos ya existan (corre después de RhPuestosCatalogoSeeder).
 */
class RhOnboardingCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('seeders/data/onboarding_catalogo.json');
        if (! File::exists($jsonPath)) {
            throw new RuntimeException("Snapshot no encontrado: {$jsonPath}. Genera con `php artisan rh:generar-seeder-onboarding`.");
        }

        $payload = json_decode(File::get($jsonPath), true);
        if (! is_array($payload) || ! isset($payload['onboarding'])) {
            throw new RuntimeException("Snapshot inválido en {$jsonPath}.");
        }

        $this->command?->info("Cargando snapshot generado el {$payload['generado_en']} ({$payload['total_puestos']} puestos, {$payload['total_tareas']} tareas).");

        $puestosPorClave = [];
        foreach (Puesto::query()->get(['id', 'nombre']) as $puesto) {
            $puestosPorClave[$this->clave($puesto->nombre)] = $puesto->id;
        }

        $enlazados = 0;
        $tareasCreadas = 0;
        $sinPuesto = [];

        DB::transaction(function () use ($payload, $puestosPorClave, &$enlazados, &$tareasCreadas, &$sinPuesto) {
            foreach ($payload['onboarding'] as $bloque) {
                $puestoId = $puestosPorClave[$this->clave((string) $bloque['puesto'])] ?? null;
                if ($puestoId === null) {
                    $sinPuesto[] = (string) $bloque['puesto'];

                    continue;
                }

                OnboardingTareaPlantilla::query()->where('puesto_id', $puestoId)->delete();

                foreach ($bloque['tareas'] as $t) {
                    OnboardingTareaPlantilla::create([
                        'puesto_id' => $puestoId,
                        'titulo' => $t['titulo'],
                        'descripcion' => $t['descripcion'] ?? null,
                        'etapa' => $t['etapa'] ?? null,
                        'responsable' => $t['responsable'] ?? null,
                        'duracion_estimada' => $t['duracion_estimada'] ?? null,
                        'dias_desde_inicio' => $t['dias_desde_inicio'] ?? null,
                        'orden' => $t['orden'] ?? 0,
                    ]);
                    $tareasCreadas++;
                }
                $enlazados++;
            }
        });

        $this->command?->info("Plantillas de onboarding sembradas: {$enlazados} puestos, {$tareasCreadas} tareas.");
        if ($sinPuesto !== []) {
            $this->command?->warn('Puestos del snapshot no encontrados en catálogo: '.count($sinPuesto));
            foreach (array_slice($sinPuesto, 0, 15) as $p) {
                $this->command?->line('  - '.$p);
            }
        }
    }

    private function clave(string $nombre): string
    {
        $s = mb_strtoupper(trim($nombre));
        if (class_exists(\Normalizer::class)) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
            $s = (string) preg_replace('/\p{Mn}+/u', '', $s);
        }

        return trim((string) preg_replace('/\s+/', ' ', $s));
    }
}
