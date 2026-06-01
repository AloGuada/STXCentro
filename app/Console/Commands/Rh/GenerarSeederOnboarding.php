<?php

namespace App\Console\Commands\Rh;

use App\Models\Rh\Puesto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerarSeederOnboarding extends Command
{
    protected $signature = 'rh:generar-seeder-onboarding';

    protected $description = 'Snapshot de las plantillas de onboarding por puesto a JSON, consumible por RhOnboardingCatalogoSeeder.';

    public function handle(): int
    {
        $puestos = Puesto::query()
            ->whereHas('plantillasOnboarding')
            ->with(['plantillasOnboarding' => fn ($q) => $q->orderBy('orden')->orderBy('id')])
            ->orderBy('nombre')
            ->get();

        $data = [];
        $totalTareas = 0;
        foreach ($puestos as $puesto) {
            $tareas = $puesto->plantillasOnboarding->map(fn ($t) => [
                'titulo' => $t->titulo,
                'descripcion' => $t->descripcion,
                'etapa' => $t->etapa,
                'responsable' => $t->responsable,
                'duracion_estimada' => $t->duracion_estimada,
                'dias_desde_inicio' => $t->dias_desde_inicio,
                'orden' => $t->orden,
            ])->all();
            $totalTareas += count($tareas);
            $data[] = [
                'puesto' => $puesto->nombre,
                'tareas' => $tareas,
            ];
        }

        $payload = [
            'version' => 1,
            'generado_en' => now()->toIso8601String(),
            'total_puestos' => count($data),
            'total_tareas' => $totalTareas,
            'onboarding' => $data,
        ];

        $targetDir = database_path('seeders/data');
        if (! File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }
        $targetFile = $targetDir.DIRECTORY_SEPARATOR.'onboarding_catalogo.json';
        File::put($targetFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Snapshot escrito a: {$targetFile}");
        $this->info("Puestos con plantilla: {$payload['total_puestos']}");
        $this->info("Tareas exportadas: {$payload['total_tareas']}");

        return self::SUCCESS;
    }
}
