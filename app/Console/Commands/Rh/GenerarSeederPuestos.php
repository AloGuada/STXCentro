<?php

namespace App\Console\Commands\Rh;

use App\Models\Rh\Puesto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerarSeederPuestos extends Command
{
    protected $signature = 'rh:generar-seeder-puestos';

    protected $description = 'Snapshot del catalogo actual de puestos a JSON, consumible por RhPuestosCatalogoSeeder.';

    public function handle(): int
    {
        $puestos = Puesto::query()
            ->with(['departamento', 'puestoJefe:id,nombre', 'skills', 'requerimientos', 'actividades'])
            ->orderBy('nombre')
            ->get();

        $data = [];
        foreach ($puestos as $p) {
            $data[] = [
                'nombre' => $p->nombre,
                'departamento' => $p->departamento?->descripcion,
                'descripcion' => $p->descripcion,
                'codigo' => $p->codigo,
                'ubicacion' => $p->ubicacion,
                'hora_entrada' => $p->hora_entrada?->format('H:i'),
                'hora_salida' => $p->hora_salida?->format('H:i'),
                'puesto_jefe' => $p->puestoJefe?->nombre,
                'skills' => $p->skills->map(fn ($s) => [
                    'nombre' => $s->nombre,
                    'tipo' => $s->tipo,
                    'nivel_requerido' => $s->pivot->nivel_requerido,
                ])->all(),
                'requerimientos' => $p->requerimientos->map(fn ($r) => [
                    'descripcion' => $r->descripcion,
                    'valor' => $r->valor,
                ])->all(),
                'actividades' => $p->actividades->pluck('descripcion')->all(),
            ];
        }

        $payload = [
            'version' => 1,
            'generado_en' => now()->toIso8601String(),
            'total_puestos' => count($data),
            'puestos' => $data,
        ];

        $targetDir = database_path('seeders/data');
        if (! File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }
        $targetFile = $targetDir.DIRECTORY_SEPARATOR.'puestos_catalogo.json';
        File::put($targetFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Snapshot escrito a: {$targetFile}");
        $this->info("Puestos exportados: {$payload['total_puestos']}");
        $skillsUnicos = collect($data)->pluck('skills')->flatten(1)->pluck('nombre')->unique()->count();
        $reqsUnicos = collect($data)->pluck('requerimientos')->flatten(1)->pluck('descripcion')->unique()->count();
        $this->info("Skills unicos referenciados: {$skillsUnicos}");
        $this->info("Requerimientos unicos referenciados: {$reqsUnicos}");
        $this->info(sprintf('Tamano archivo: %d KB', (int) (filesize($targetFile) / 1024)));

        return self::SUCCESS;
    }
}
