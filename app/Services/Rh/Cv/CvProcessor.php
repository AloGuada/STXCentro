<?php

namespace App\Services\Rh\Cv;

use App\Models\Rh\Persona;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CvProcessor
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PROCESANDO = 'procesando';

    public const ESTADO_TEXTO_EXTRAIDO = 'texto_extraido';

    public const ESTADO_DATOS_EXTRAIDOS = 'datos_extraidos';

    public const ESTADO_REQUISITOS_PROCESADOS = 'requisitos_procesados';

    public const ESTADO_COMPLETADO = 'completado';

    public const ESTADO_ERROR = 'error';

    private const MAX_REINTENTOS = 3;

    public function __construct(
        private readonly CvTextExtractor $textExtractor,
        private readonly CvDataExtractor $dataExtractor,
        private readonly CvRequerimientoMatcher $reqMatcher,
        private readonly CvSkillMatcher $skillMatcher,
    ) {}

    /**
     * Procesa el siguiente paso pendiente de UNA persona.
     * Retorna el ID de la persona procesada, o null si no había nada pendiente.
     */
    public function procesarSiguiente(): ?int
    {
        $reserved = $this->reservarSiguiente();
        if ($reserved === null) {
            return null;
        }

        [$persona, $estadoPrevio] = $reserved;

        try {
            $this->ejecutarPaso($persona, $estadoPrevio);
            $persona->update(['reintentos' => 0, 'error_procesamiento' => null]);
        } catch (Throwable $e) {
            $this->manejarError($persona, $estadoPrevio, $e);
        }

        return $persona->id;
    }

    /** @return array{0: Persona, 1: string}|null */
    private function reservarSiguiente(): ?array
    {
        return DB::transaction(function () {
            // Liberar procesos colgados >30 min
            Persona::whereIn('cv_estado', [
                self::ESTADO_PROCESANDO,
                self::ESTADO_TEXTO_EXTRAIDO,
                self::ESTADO_DATOS_EXTRAIDOS,
                self::ESTADO_REQUISITOS_PROCESADOS,
            ])->where('updated_at', '<', now()->subMinutes(30))
                ->update(['cv_estado' => self::ESTADO_PENDIENTE]);

            $persona = Persona::query()
                ->whereIn('cv_estado', [
                    self::ESTADO_REQUISITOS_PROCESADOS,
                    self::ESTADO_DATOS_EXTRAIDOS,
                    self::ESTADO_TEXTO_EXTRAIDO,
                    self::ESTADO_PENDIENTE,
                ])
                ->whereHas('media')
                ->orderByRaw("CASE cv_estado
                    WHEN 'requisitos_procesados' THEN 1
                    WHEN 'datos_extraidos' THEN 2
                    WHEN 'texto_extraido' THEN 3
                    WHEN 'pendiente' THEN 4
                    ELSE 5 END")
                ->oldest('updated_at')
                ->lockForUpdate()
                ->first();

            if ($persona === null) {
                return null;
            }

            $estadoPrevio = (string) $persona->cv_estado;
            $persona->update(['cv_estado' => self::ESTADO_PROCESANDO]);

            return [$persona, $estadoPrevio];
        });
    }

    private function ejecutarPaso(Persona $persona, string $estadoPrevio): void
    {
        Log::info('CV proceso paso', ['persona_id' => $persona->id, 'estado' => $estadoPrevio]);

        match ($estadoPrevio) {
            self::ESTADO_PENDIENTE => $this->paso1ExtraerTexto($persona),
            self::ESTADO_TEXTO_EXTRAIDO => $this->paso2ExtraerDatos($persona),
            self::ESTADO_DATOS_EXTRAIDOS => $this->paso3MatchRequerimientos($persona),
            self::ESTADO_REQUISITOS_PROCESADOS => $this->paso4MatchSkillsYCompletar($persona),
            default => throw new RuntimeException("Estado inesperado: {$estadoPrevio}"),
        };
    }

    private function paso1ExtraerTexto(Persona $persona): void
    {
        $cvPath = $this->resolverRutaCv($persona);
        $texto = $this->textExtractor->extract($cvPath);
        $persona->update(['texto_cv' => $texto, 'cv_estado' => self::ESTADO_TEXTO_EXTRAIDO]);
    }

    private function paso2ExtraerDatos(Persona $persona): void
    {
        $texto = (string) $persona->texto_cv;
        if ($texto === '') {
            throw new RuntimeException('Persona en texto_extraido pero sin texto_cv');
        }
        $this->dataExtractor->extractAndStore($persona, $texto);
        $persona->update(['cv_estado' => self::ESTADO_DATOS_EXTRAIDOS]);
    }

    private function paso3MatchRequerimientos(Persona $persona): void
    {
        $texto = (string) $persona->texto_cv;
        if ($texto === '') {
            throw new RuntimeException('Persona sin texto_cv');
        }
        $candidatura = $persona->candidaturas()->first();
        $this->reqMatcher->match($persona, $texto, $candidatura);
        $persona->update(['cv_estado' => self::ESTADO_REQUISITOS_PROCESADOS]);
    }

    private function paso4MatchSkillsYCompletar(Persona $persona): void
    {
        $texto = (string) $persona->texto_cv;
        if ($texto === '') {
            throw new RuntimeException('Persona sin texto_cv');
        }
        $candidatura = $persona->candidaturas()->first();
        $this->skillMatcher->match($persona, $texto, $candidatura);

        $persona->update([
            'cv_estado' => self::ESTADO_COMPLETADO,
            'cv_procesado_at' => now(),
        ]);

        $persona->load('candidaturas.requisicion.puesto.skills', 'candidaturas.requisicion.puesto.requerimientos');
        foreach ($persona->candidaturas as $cand) {
            try {
                $cand->calcularPorcentajes();
            } catch (Throwable $e) {
                Log::warning('Error calculando porcentajes', ['candidatura_id' => $cand->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function resolverRutaCv(Persona $persona): string
    {
        $persona->loadMissing('media');
        $mediaPath = $persona->media?->path;
        if ($mediaPath === null) {
            throw new RuntimeException("Persona {$persona->id} no tiene CV (media)");
        }
        if (! Storage::disk('public')->exists($mediaPath)) {
            throw new RuntimeException("Archivo CV no existe en disco: {$mediaPath}");
        }

        return (string) Storage::disk('public')->path($mediaPath);
    }

    private function manejarError(Persona $persona, string $estadoPrevio, Throwable $e): void
    {
        $intentos = ((int) $persona->reintentos) + 1;

        if ($intentos >= self::MAX_REINTENTOS) {
            $persona->update([
                'cv_estado' => self::ESTADO_ERROR,
                'error_procesamiento' => sprintf('Tras %d intentos: %s', $intentos, $e->getMessage()),
                'reintentos' => $intentos,
            ]);
            Log::error('CV falló tras máximo de reintentos', ['persona_id' => $persona->id, 'error' => $e->getMessage()]);

            return;
        }

        $persona->update([
            'cv_estado' => $estadoPrevio,
            'error_procesamiento' => $e->getMessage(),
            'reintentos' => $intentos,
        ]);
        Log::warning('CV paso falló, reintento programado', [
            'persona_id' => $persona->id,
            'estado' => $estadoPrevio,
            'intento' => $intentos,
            'max' => self::MAX_REINTENTOS,
        ]);
    }
}
