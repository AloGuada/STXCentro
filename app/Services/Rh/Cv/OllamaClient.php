<?php

namespace App\Services\Rh\Cv;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OllamaClient
{
    public function __construct(
        private readonly string $url,
        private readonly string $model,
        private readonly int $timeout,
        private readonly int $maxRetries,
        private readonly float $temperature,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            url: (string) config('services.ollama.url'),
            model: (string) config('services.ollama.model'),
            timeout: (int) config('services.ollama.timeout'),
            maxRetries: (int) config('services.ollama.max_retries'),
            temperature: (float) config('services.ollama.temperature'),
        );
    }

    /**
     * @param  array<string, mixed>|null  $jsonSchema
     */
    public function generate(string $prompt, ?string $system = null, ?array $jsonSchema = null, string $contexto = 'general'): string
    {
        $prompt = $this->limpiarUtf8($prompt);
        $system = $system !== null ? $this->limpiarUtf8($system) : null;

        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'system' => $system ?? '',
            'stream' => false,
            'temperature' => $this->temperature,
        ];

        if ($jsonSchema !== null) {
            $payload['format'] = $jsonSchema;
        }

        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                $startMs = microtime(true);
                $response = Http::timeout($this->timeout)->post($this->url, $payload);
                $durationMs = (int) round((microtime(true) - $startMs) * 1000);

                if ($response->successful()) {
                    $result = (string) ($response->json()['response'] ?? '');
                    Log::info('ollama ok', ['ctx' => $contexto, 'attempt' => $attempt, 'duration_ms' => $durationMs, 'len' => strlen($result)]);

                    return $result;
                }

                $lastError = sprintf('HTTP %d: %s', $response->status(), $response->body());
                Log::warning('ollama http error', ['ctx' => $contexto, 'attempt' => $attempt, 'error' => $lastError]);
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning('ollama exception', ['ctx' => $contexto, 'attempt' => $attempt, 'error' => $lastError]);
            }

            if ($attempt < $this->maxRetries) {
                sleep(2);
            }
        }

        throw new RuntimeException("Ollama falló después de {$this->maxRetries} intentos [{$contexto}]: {$lastError}");
    }

    private function limpiarUtf8(string $texto): string
    {
        $encoding = mb_detect_encoding($texto, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $texto = (string) mb_convert_encoding($texto, 'UTF-8', $encoding);
        }
        $texto = (string) mb_convert_encoding($texto, 'UTF-8', 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $texto = (string) \Normalizer::normalize($texto, \Normalizer::FORM_C);
        }
        $texto = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto);

        return trim($texto);
    }
}
