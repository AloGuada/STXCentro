<?php

namespace App\Services\Rh\Cv;

use App\Models\Rh\Candidatura;
use App\Models\Rh\Persona;
use App\Models\Rh\Skill;
use App\Models\Rh\SkillDemostrada;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CvSkillMatcher
{
    public function __construct(private readonly OllamaClient $ollama) {}

    public function match(Persona $persona, string $textoCv, ?Candidatura $candidatura = null): void
    {
        $skills = $this->resolverSkills($candidatura);
        if ($skills->isEmpty()) {
            Log::info('Sin skills para evaluar', ['persona_id' => $persona->id]);

            return;
        }

        $resultados = $this->llamarOllama($textoCv, $skills);
        if ($resultados === []) {
            throw new RuntimeException('Ollama no devolvió resultados de skills');
        }

        $idsValidos = $skills->pluck('id')->all();
        foreach ($resultados as $r) {
            $skillId = (int) ($r['id'] ?? 0);
            if (! in_array($skillId, $idsValidos, true)) {
                continue;
            }
            SkillDemostrada::updateOrCreate(
                ['persona_id' => $persona->id, 'skill_id' => $skillId],
                ['cumple' => (bool) ($r['cumple'] ?? false), 'nivel_alcanzado' => 'basico'],
            );
        }
    }

    /** @return \Illuminate\Support\Collection<int, Skill> */
    private function resolverSkills(?Candidatura $candidatura): \Illuminate\Support\Collection
    {
        if ($candidatura !== null) {
            $candidatura->loadMissing('requisicion.puesto.skills');
            $skills = $candidatura->requisicion?->puesto?->skills;
            if ($skills !== null && $skills->isNotEmpty()) {
                return collect($skills->all());
            }
        }

        return Skill::query()->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Skill>  $skills
     * @return list<array<string, mixed>>
     */
    private function llamarOllama(string $textoCv, \Illuminate\Support\Collection $skills): array
    {
        $payload = $skills->map(fn (Skill $s) => [
            'id' => $s->id,
            'nombre' => (string) $s->nombre,
        ])->all();

        $skillsJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $system = 'Eres un asistente experto en análisis de habilidades. Determina si el candidato posee las skills de la lista. Marca como cumplida una skill si:
1. Está mencionada explícitamente en el CV, O
2. Se puede inferir porque domina una habilidad relacionada (ej: si redacta informes ejecutivos obviamente sabe Word).

Reglas:
- NUNCA inventes skills fuera de la lista proporcionada.
- SOLO marca skills de la lista.
- Responde en formato JSON.';

        $prompt = <<<PROMPT
Analiza el siguiente CV y evalúa si la persona demuestra tener cada una de estas skills.

SKILLS A EVALUAR:
{$skillsJson}

CV:
{$textoCv}

INSTRUCCIONES:
- Marca "cumple": true si hay evidencia clara en el CV (experiencia, proyectos, certificaciones).
- Marca "cumple": false si no hay evidencia o no se menciona.

Responde ÚNICAMENTE con un array JSON sin markdown:
[{"id": 1, "cumple": true}, ...]
PROMPT;

        $schema = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'cumple' => ['type' => 'boolean'],
                ],
                'required' => ['id', 'cumple'],
            ],
        ];

        $respuesta = $this->ollama->generate($prompt, $system, $schema, 'cv_skills');

        return $this->parsearArray($respuesta);
    }

    /** @return list<array<string, mixed>> */
    private function parsearArray(string $respuesta): array
    {
        $respuesta = (string) preg_replace('/```json\s*|\s*```/', '', $respuesta);
        if (preg_match('/\[.*\]/s', $respuesta, $matches)) {
            $json = json_decode($matches[0], true);
            if (is_array($json)) {
                return $json;
            }
        }
        Log::warning('No se pudo parsear array de skills', ['respuesta' => substr($respuesta, 0, 500)]);

        return [];
    }
}
