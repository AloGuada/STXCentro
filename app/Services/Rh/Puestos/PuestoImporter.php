<?php

namespace App\Services\Rh\Puestos;

use App\Models\Departamento;
use App\Models\Rh\Actividad;
use App\Models\Rh\Puesto;
use App\Models\Rh\Requerimiento;
use App\Models\Rh\Skill;
use Illuminate\Support\Facades\DB;

/**
 * Persiste los bloques parseados a la BD de forma idempotente.
 * Caché interno de skills/requerimientos para no duplicar lookups.
 */
class PuestoImporter
{
    /** @var array<string, int> dedupKey => skill_id */
    private array $skillsCache = [];

    /** @var array<string, int> dedupKey => requerimiento_id */
    private array $reqsCache = [];

    /** @var array<string, int> nombre normalizado => departamento_id */
    private array $deptCache = [];

    /** @var array<string, int> nombre puesto en uppercase => puesto_id */
    private array $puestosCreados = [];

    public function __construct(private readonly PuestoNormalizer $normalizer) {}

    /**
     * Importa un bloque parseado y retorna el puesto creado/actualizado.
     * Si $dryRun=true, no escribe a BD pero igual decide qué se haría.
     *
     * @param  array<string, mixed>  $bloque
     * @return array{puesto_id: ?int, accion: string, skills: int, reqs: int, actividades: int}
     */
    public function importBloque(array $bloque, bool $dryRun = false): array
    {
        $nombre = (string) ($bloque['nombre'] ?? '');
        if ($nombre === '') {
            return ['puesto_id' => null, 'accion' => 'skip_sin_nombre', 'skills' => 0, 'reqs' => 0, 'actividades' => 0];
        }

        $nombreDisplay = $this->normalizer->displayName($nombre);
        $deptId = $this->resolverDepartamento($bloque['departamento_nombre'] ?? null, $dryRun);
        [$entrada, $salida] = $this->normalizer->parsearHorario($bloque['horario_texto'] ?? null);

        $key = strtoupper($nombreDisplay);

        return DB::transaction(function () use ($bloque, $nombreDisplay, $deptId, $entrada, $salida, $key, $dryRun) {
            $accion = 'created';
            $puestoId = null;

            if (! $dryRun) {
                $puesto = Puesto::firstOrNew(['nombre' => $nombreDisplay]);
                if ($puesto->exists) {
                    $accion = 'updated';
                }
                $puesto->fill([
                    'departamento_id' => $deptId,
                    'descripcion' => $bloque['objetivo'] ?? $puesto->descripcion,
                    'ubicacion' => $bloque['ubicacion'] ?? $puesto->ubicacion,
                    'hora_entrada' => $entrada ?? $puesto->hora_entrada,
                    'hora_salida' => $salida ?? $puesto->hora_salida,
                ])->save();
                $puestoId = $puesto->id;
                $this->puestosCreados[$key] = $puestoId;
            }

            // Skills
            $skillsAttach = $this->prepararSkills($bloque['skills_soft'] ?? [], 'soft', $dryRun)
                + $this->prepararSkills($bloque['skills_hard'] ?? [], 'hard', $dryRun);

            if (! $dryRun && $puestoId !== null && $skillsAttach !== []) {
                Puesto::find($puestoId)->skills()->syncWithoutDetaching($skillsAttach);
            }

            // Requerimientos (solo de la seccion REQUISITOS DEL PUESTO: requisitos KV + experiencia + certificaciones).
            // La seccion REQUERIMIENTOS del Excel (computadora/telefono/auto/EPP) se IGNORA — son recursos del puesto, no requisitos para la persona.
            $reqIds = array_merge(
                $this->prepararRequerimientos($bloque['experiencia'] ?? [], 'Experiencia', null, $dryRun),
                $this->prepararRequerimientos($bloque['certificaciones'] ?? [], 'Certificación', null, $dryRun),
                $this->prepararRequerimientosKV($bloque['requisitos'] ?? [], $dryRun),
            );

            if (! $dryRun && $puestoId !== null && $reqIds !== []) {
                Puesto::find($puestoId)->requerimientos()->syncWithoutDetaching($reqIds);
            }

            // Actividades: idempotencia "best-effort" (firstOrCreate por descripcion + puesto_id)
            $actividadesCount = 0;
            foreach ($bloque['actividades'] ?? [] as $desc) {
                if (mb_strlen(trim((string) $desc)) < 3) {
                    continue;
                }
                $actividadesCount++;
                if (! $dryRun && $puestoId !== null) {
                    Actividad::firstOrCreate([
                        'puesto_id' => $puestoId,
                        'descripcion' => $desc,
                    ]);
                }
            }

            return [
                'puesto_id' => $puestoId,
                'accion' => $accion,
                'skills' => count($skillsAttach),
                'reqs' => count($reqIds),
                'actividades' => $actividadesCount,
            ];
        });
    }

    private function resolverDepartamento(?string $nombre, bool $dryRun): ?int
    {
        if ($nombre === null) {
            return null;
        }
        $nombre = $this->normalizer->displayName($nombre);
        $key = mb_strtoupper($nombre);
        if (isset($this->deptCache[$key])) {
            return $this->deptCache[$key];
        }
        if ($dryRun) {
            $this->deptCache[$key] = -1;

            return -1; // marker; no se persiste FK falsa en dry-run
        }
        $dept = Departamento::firstOrCreate(['descripcion' => $nombre]);
        $this->deptCache[$key] = (int) $dept->id;

        return (int) $dept->id;
    }

    /**
     * @param  list<array{nombre: string, nivel: string}>  $items
     * @return array<int, array{nivel_requerido: string}> skill_id => pivot
     */
    private function prepararSkills(array $items, string $tipoSeccion, bool $dryRun): array
    {
        $out = [];
        foreach ($items as $it) {
            $crudo = (string) ($it['nombre'] ?? '');
            $nombre = $this->normalizer->limpiarItem($crudo);
            if ($nombre === null || $this->normalizer->esSkillBasura($nombre)) {
                continue;
            }
            $key = $this->normalizer->dedupKey($nombre);
            $tipo = $this->normalizer->clasificarSkill($nombre, $tipoSeccion);
            $nivel = $it['nivel'] ?? 'basico';

            if (! isset($this->skillsCache[$key])) {
                if ($dryRun) {
                    $this->skillsCache[$key] = count($this->skillsCache) * -1 - 1;
                } else {
                    $skill = Skill::firstOrCreate(
                        ['nombre' => $this->normalizer->displayName($nombre)],
                        ['tipo' => $tipo],
                    );
                    $this->skillsCache[$key] = (int) $skill->id;
                }
            }
            $skillId = $this->skillsCache[$key];
            $out[$skillId] = ['nivel_requerido' => $nivel];
        }

        return $out;
    }

    /**
     * @param  list<string>  $items
     * @return list<int>
     */
    private function prepararRequerimientos(array $items, string $prefijoDesc, ?string $valor, bool $dryRun): array
    {
        $out = [];
        foreach ($items as $crudo) {
            $desc = $this->normalizer->limpiarItem((string) $crudo);
            if ($desc === null || mb_strtoupper($desc) === 'N/A') {
                continue;
            }
            $descripcionFinal = $prefijoDesc.': '.$desc;
            $key = $this->normalizer->dedupKey($descripcionFinal);
            if (! isset($this->reqsCache[$key])) {
                if ($dryRun) {
                    $this->reqsCache[$key] = count($this->reqsCache) * -1 - 1;
                } else {
                    $req = Requerimiento::firstOrCreate(
                        ['descripcion' => $descripcionFinal],
                        ['valor' => $valor],
                    );
                    $this->reqsCache[$key] = (int) $req->id;
                }
            }
            $out[] = $this->reqsCache[$key];
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array<string, string|null>  $kv
     * @return list<int>
     */
    private function prepararRequerimientosKV(array $kv, bool $dryRun): array
    {
        $out = [];
        foreach ($kv as $label => $valor) {
            $valor = $this->normalizer->normalizarValorRequerimiento((string) $valor);
            if ($valor === null) {
                continue;
            }
            $label = (string) $label;
            $upper = mb_strtoupper($label);
            if ($upper === 'GENERO' || $upper === 'GÉNERO') {
                $valor = $this->normalizer->normalizarGenero($valor) ?? $valor;
            }
            if ($upper === 'NIVEL_ESCOLARIDAD' || $upper === 'NIVEL DE ESCOLARIDAD') {
                $valor = $this->normalizer->normalizarEscolaridad($valor) ?? $valor;
            }
            $descripcionFinal = $this->normalizer->displayName($label);
            $key = $this->normalizer->dedupKey($descripcionFinal.'|'.$valor);
            if (! isset($this->reqsCache[$key])) {
                if ($dryRun) {
                    $this->reqsCache[$key] = count($this->reqsCache) * -1 - 1;
                } else {
                    $req = Requerimiento::firstOrCreate(
                        ['descripcion' => $descripcionFinal, 'valor' => $valor],
                    );
                    $this->reqsCache[$key] = (int) $req->id;
                }
            }
            $out[] = $this->reqsCache[$key];
        }

        return array_values(array_unique($out));
    }

    /**
     * Segunda pasada: enlaza puesto_jefe_id a partir del nombre del jefe inmediato.
     * Devuelve [matched, unmatched].
     *
     * @param  list<array{puesto_id: int, jefe_nombre: string|null}>  $pendientes
     * @return array{matched: int, unmatched: list<string>}
     */
    public function enlazarJefes(array $pendientes, bool $dryRun = false): array
    {
        $matched = 0;
        $unmatched = [];
        foreach ($pendientes as $p) {
            $jefeNombre = $p['jefe_nombre'] ?? null;
            if ($jefeNombre === null) {
                continue;
            }
            $jefeKey = strtoupper($this->normalizer->displayName($jefeNombre));
            $jefeId = $this->puestosCreados[$jefeKey] ?? null;
            if ($jefeId === null && ! $dryRun) {
                // Fallback: buscar en BD por nombre case-insensitive
                $jefe = Puesto::query()->whereRaw('LOWER(nombre) = ?', [strtolower($jefeKey)])->first();
                $jefeId = $jefe?->id;
            }
            if ($jefeId === null) {
                $unmatched[] = $jefeNombre;

                continue;
            }
            $matched++;
            if (! $dryRun) {
                Puesto::where('id', $p['puesto_id'])->update(['puesto_jefe_id' => $jefeId]);
            }
        }

        return ['matched' => $matched, 'unmatched' => array_values(array_unique($unmatched))];
    }

    public function resetCaches(): void
    {
        $this->skillsCache = [];
        $this->reqsCache = [];
        $this->deptCache = [];
        $this->puestosCreados = [];
    }
}
