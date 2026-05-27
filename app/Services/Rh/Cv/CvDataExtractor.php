<?php

namespace App\Services\Rh\Cv;

use App\Models\Rh\Persona;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CvDataExtractor
{
    public function __construct(private readonly OllamaClient $ollama) {}

    public function extractAndStore(Persona $persona, string $textoCv): void
    {
        $datos = $this->llamarOllama($textoCv);
        $this->guardar($persona, $datos);
    }

    /** @return array<string, mixed> */
    private function llamarOllama(string $textoCv): array
    {
        $system = 'Eres un asistente experto en extracción de datos de CVs. Analiza el texto del currículum y extrae únicamente la información personal solicitada en formato JSON válido. No inventes datos que no estén presentes.';

        $prompt = <<<PROMPT
Analiza el siguiente CV y extrae la información en formato JSON.

IMPORTANTE - NOMBRE Y APELLIDO:
- Si encuentras un nombre completo (ej: "Jorge Miguel Briones Arjona"), divide correctamente:
  * nombre: Solo el/los nombre(s) de pila (ej: "Jorge Miguel")
  * apellido: Solo los apellidos (ej: "Briones Arjona")
- En nombres mexicanos típicos: Primer(os) nombre(s) + Apellido Paterno + Apellido Materno
- Si solo aparece un nombre, ponlo en "nombre" y deja apellido vacío
- Si no encuentras el nombre, usa "Pendiente" en nombre y "de Extracción" en apellido

Extrae SOLO estos campos:
- nombre, apellido, email, telefono
- fecha_nacimiento: formato YYYY-MM-DD
- estado_civil: soltero, casado, divorciado, viudo, union_libre
- hijos: número entero (cantidad de hijos)
- localidad, domicilio, cp
- nombre_padre, nombre_madre
- curp (18 chars), rfc (12 o 13 chars)

CV:
{$textoCv}

Responde ÚNICAMENTE con un JSON válido, sin explicaciones adicionales ni bloques de código markdown.
PROMPT;

        $schema = [
            'type' => 'object',
            'properties' => [
                'nombre' => ['type' => 'string'],
                'apellido' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'telefono' => ['type' => 'string'],
                'fecha_nacimiento' => ['type' => 'string'],
                'estado_civil' => ['type' => 'string'],
                'hijos' => ['type' => 'integer'],
                'localidad' => ['type' => 'string'],
                'domicilio' => ['type' => 'string'],
                'cp' => ['type' => 'string'],
                'nombre_padre' => ['type' => 'string'],
                'nombre_madre' => ['type' => 'string'],
                'curp' => ['type' => 'string'],
                'rfc' => ['type' => 'string'],
            ],
            'required' => ['nombre', 'apellido'],
        ];

        $respuesta = $this->ollama->generate($prompt, $system, $schema, 'cv_datos_personales');

        return $this->parsearJson($respuesta);
    }

    /** @return array<string, mixed> */
    private function parsearJson(string $respuesta): array
    {
        $respuesta = (string) preg_replace('/```json\s*|\s*```/', '', $respuesta);
        if (preg_match('/\{.*\}/s', $respuesta, $matches)) {
            $json = json_decode($matches[0], true);
            if (is_array($json)) {
                return $json;
            }
        }
        Log::warning('No se pudo parsear JSON de datos personales', ['respuesta' => substr($respuesta, 0, 500)]);

        return [];
    }

    /** @param array<string, mixed> $datos */
    private function guardar(Persona $persona, array $datos): void
    {
        $update = [];

        // Básicos: solo actualizar si vienen y la persona no tiene
        foreach (['nombre', 'apellido', 'email', 'telefono'] as $campo) {
            $valor = $this->limpiar($datos[$campo] ?? null);
            if ($valor !== null && empty($persona->{$campo})) {
                $update[$campo] = $valor;
            }
        }

        if (! empty($datos['fecha_nacimiento'])) {
            try {
                $fecha = Carbon::parse((string) $datos['fecha_nacimiento']);
                if ($fecha->year >= 1900 && $fecha->year <= now()->year) {
                    $update['fecha_nacimiento'] = $fecha->format('Y-m-d');
                }
            } catch (\Throwable) {
                // ignorar fecha inválida
            }
        }

        // Extras: siempre actualizar si vienen
        foreach (['estado_civil', 'localidad', 'domicilio', 'cp', 'nombre_padre', 'nombre_madre', 'curp', 'rfc'] as $campo) {
            $valor = $this->limpiar($datos[$campo] ?? null);
            if ($valor !== null) {
                $update[$campo] = $valor;
            }
        }

        if (isset($datos['hijos'])) {
            $update['hijos'] = is_numeric($datos['hijos']) ? (int) $datos['hijos'] : null;
        }

        if ($update !== []) {
            $persona->update($update);
        }
    }

    private function limpiar(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        if (is_string($valor)) {
            $valor = trim($valor);

            return $valor === '' ? null : $valor;
        }

        return (string) $valor;
    }
}
