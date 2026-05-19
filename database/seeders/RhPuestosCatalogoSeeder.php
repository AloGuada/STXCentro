<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Rh\Actividad;
use App\Models\Rh\Puesto;
use App\Models\Rh\Requerimiento;
use App\Models\Rh\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class RhPuestosCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('seeders/data/puestos_catalogo.json');
        if (! File::exists($jsonPath)) {
            throw new RuntimeException("Snapshot no encontrado: {$jsonPath}. Genera con `php artisan rh:generar-seeder-puestos`.");
        }

        $payload = json_decode(File::get($jsonPath), true);
        if (! is_array($payload) || ! isset($payload['puestos'])) {
            throw new RuntimeException("Snapshot inválido en {$jsonPath}.");
        }

        $this->command->info("Cargando snapshot generado el {$payload['generado_en']} ({$payload['total_puestos']} puestos).");

        $skillsCache = [];   // nombre normalizado => Skill
        $reqsCache = [];     // descripcion|valor => Requerimiento
        $deptsCache = [];    // descripcion => Departamento

        $puestosCreados = [];   // nombre => Puesto

        DB::transaction(function () use ($payload, &$skillsCache, &$reqsCache, &$deptsCache, &$puestosCreados) {
            // 1ra pasada: crear puestos + asociar skills/reqs/actividades.
            foreach ($payload['puestos'] as $p) {
                $deptKey = (string) ($p['departamento'] ?? '');
                $deptId = null;
                if ($deptKey !== '') {
                    if (! isset($deptsCache[$deptKey])) {
                        $deptsCache[$deptKey] = Departamento::firstOrCreate(['descripcion' => $deptKey]);
                    }
                    $deptId = $deptsCache[$deptKey]->id;
                }

                $puesto = Puesto::firstOrNew(['nombre' => $p['nombre']]);
                $puesto->fill([
                    'departamento_id' => $deptId,
                    'descripcion' => $p['descripcion'] ?? $puesto->descripcion,
                    'codigo' => $p['codigo'] ?? $puesto->codigo,
                    'ubicacion' => $p['ubicacion'] ?? $puesto->ubicacion,
                    'hora_entrada' => $p['hora_entrada'] ?? $puesto->hora_entrada,
                    'hora_salida' => $p['hora_salida'] ?? $puesto->hora_salida,
                ])->save();

                $puestosCreados[mb_strtolower($p['nombre'])] = $puesto;

                // Skills
                $skillPivot = [];
                foreach ($p['skills'] ?? [] as $s) {
                    $key = mb_strtolower(trim($s['nombre']));
                    if (! isset($skillsCache[$key])) {
                        $skillsCache[$key] = Skill::firstOrCreate(
                            ['nombre' => $s['nombre']],
                            ['tipo' => $s['tipo'] ?? 'hard'],
                        );
                    }
                    $skillPivot[$skillsCache[$key]->id] = ['nivel_requerido' => $s['nivel_requerido'] ?? 'basico'];
                }
                if ($skillPivot !== []) {
                    $puesto->skills()->syncWithoutDetaching($skillPivot);
                }

                // Requerimientos
                $reqIds = [];
                foreach ($p['requerimientos'] ?? [] as $r) {
                    $key = mb_strtolower(trim($r['descripcion'])).'|'.(string) ($r['valor'] ?? '');
                    if (! isset($reqsCache[$key])) {
                        $reqsCache[$key] = Requerimiento::firstOrCreate(
                            ['descripcion' => $r['descripcion'], 'valor' => $r['valor'] ?? null],
                        );
                    }
                    $reqIds[] = $reqsCache[$key]->id;
                }
                if ($reqIds !== []) {
                    $puesto->requerimientos()->syncWithoutDetaching(array_values(array_unique($reqIds)));
                }

                // Actividades (idempotente por puesto + descripción)
                foreach ($p['actividades'] ?? [] as $desc) {
                    $desc = trim((string) $desc);
                    if ($desc === '') {
                        continue;
                    }
                    Actividad::firstOrCreate([
                        'puesto_id' => $puesto->id,
                        'descripcion' => $desc,
                    ]);
                }
            }

            // 2da pasada: enlazar puesto_jefe_id por nombre.
            foreach ($payload['puestos'] as $p) {
                $jefeNombre = $p['puesto_jefe'] ?? null;
                if ($jefeNombre === null) {
                    continue;
                }
                $jefe = $puestosCreados[mb_strtolower($jefeNombre)]
                    ?? Puesto::whereRaw('LOWER(nombre) = ?', [mb_strtolower($jefeNombre)])->first();
                if ($jefe === null) {
                    continue;
                }
                Puesto::where('nombre', $p['nombre'])->update(['puesto_jefe_id' => $jefe->id]);
            }
        });

        $this->command->info(sprintf(
            'Catálogo cargado: %d puestos, %d skills, %d requerimientos.',
            count($puestosCreados),
            count($skillsCache),
            count($reqsCache),
        ));
    }
}
