<?php

namespace App\Services\Rh\Cv;

use App\Models\Rh\Candidatura;
use App\Models\Rh\Persona;
use App\Models\Rh\Requerimiento;
use App\Models\Rh\RequerimientoDemostrado;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CvRequerimientoMatcher
{
    public function __construct(private readonly OllamaClient $ollama) {}

    public function match(Persona $persona, string $textoCv, ?Candidatura $candidatura = null): void
    {
        $requerimientos = $this->resolverRequerimientos($candidatura);
        if ($requerimientos->isEmpty()) {
            Log::info('Sin requerimientos para evaluar', ['persona_id' => $persona->id]);

            return;
        }

        $resultados = $this->llamarOllama($textoCv, $requerimientos);
        if ($resultados === []) {
            throw new RuntimeException('Ollama no devolvió resultados de requerimientos');
        }

        $idsValidos = $requerimientos->pluck('id')->all();
        foreach ($resultados as $r) {
            $reqId = (int) ($r['id'] ?? 0);
            if (! in_array($reqId, $idsValidos, true)) {
                continue;
            }
            RequerimientoDemostrado::updateOrCreate(
                ['persona_id' => $persona->id, 'requerimiento_id' => $reqId],
                ['cumple' => (bool) ($r['cumple'] ?? false)],
            );
        }
    }

    /** @return \Illuminate\Support\Collection<int, Requerimiento> */
    private function resolverRequerimientos(?Candidatura $candidatura): \Illuminate\Support\Collection
    {
        if ($candidatura !== null) {
            $candidatura->loadMissing('requisicion.puesto.requerimientos');
            $reqs = $candidatura->requisicion?->puesto?->requerimientos;
            if ($reqs !== null && $reqs->isNotEmpty()) {
                return collect($reqs->all());
            }
        }

        return Requerimiento::query()->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Requerimiento>  $reqs
     * @return list<array<string, mixed>>
     */
    private function llamarOllama(string $textoCv, \Illuminate\Support\Collection $reqs): array
    {
        $payload = $reqs->map(fn (Requerimiento $r) => [
            'id' => $r->id,
            'descripcion' => (string) $r->descripcion,
            'valor' => (string) ($r->valor ?? ''),
        ])->all();

        $reqsJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $system = 'Eres un asistente experto en análisis de CVs. Evalúa si el candidato cumple con cada requisito de la lista proporcionada basándote en evidencia concreta del CV.
- SOLO evalúa los requisitos de la lista proporcionada.
- NUNCA inventes ni asumas requisitos no mencionados.
- Marca como cumplido únicamente si hay evidencia clara en el CV.
- Responde en formato JSON.';

        $prompt = <<<PROMPT
Analiza el siguiente CV y evalúa si cumple con cada requerimiento.

REQUERIMIENTOS A EVALUAR:
{$reqsJson}

CV:
{$textoCv}

INSTRUCCIONES:
- Para cada requerimiento, determina si la persona lo cumple basándote en su CV.
- Marca "cumple": true si el CV demuestra que cumple con el requerimiento.
- Marca "cumple": false si no hay evidencia o no cumple.

Responde ÚNICAMENTE con un array JSON en el siguiente formato (sin markdown, sin explicaciones):
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

        $respuesta = $this->ollama->generate($prompt, $system, $schema, 'cv_requerimientos');

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
        Log::warning('No se pudo parsear array de requerimientos', ['respuesta' => substr($respuesta, 0, 500)]);

        return [];
    }
}
