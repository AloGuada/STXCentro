<?php

namespace App\Services\Qal;

use App\Enums\Qal\FaseTransformacion;
use App\Models\Qal\ConfiguracionQal;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Programacion;
use App\Models\Qal\ProgramacionPieza;
use Illuminate\Database\Eloquent\Builder;

/**
 * Qué piezas se pueden inspeccionar según el avance de producción.
 *
 * Sólo muerde con `formularios_segun_avance` encendido en la configuración de
 * Calidad, y sólo en 2ª y 3ª: la 1ª no tiene plan. Cada fase se mide contra su
 * propio plan —armado y soldado contra el de 2ª, pintura contra el de 3ª—, por
 * obra y por la semana en curso.
 *
 * Una pieza está habilitada si Producción la programó en un plan cerrado de
 * esta semana o de una anterior (lo atrasado sigue valiendo hasta hacerse), o
 * si ya tiene una inspección en esa fase: la que pasa de armado a soldado y la
 * rechazada que vuelve reparada no se quedan a medias. Pero sin el plan de esta
 * semana cerrado no se habilita nada de esa fase: el cierre es la señal de que
 * Producción ya dijo qué se hace.
 *
 * El cruce es por el QR escrito, igual que el avance: el catálogo se versiona y
 * el mismo QR vive en varias versiones.
 */
class PiezasHabilitadas
{
    /** @var array<string, array{plan_cerrado: bool, qrs: array<string, true>}> */
    private array $calculadas = [];

    private ?bool $activo = null;

    public function activo(): bool
    {
        return $this->activo ??= ConfiguracionQal::actual()->formularios_segun_avance;
    }

    /**
     * Lo habilitado de una obra en una fase, o null si esa fase no se filtra
     * (interruptor apagado o 1ª transformación).
     *
     * @return array{plan_cerrado: bool, qrs: array<string, true>}|null
     */
    public function deFase(int $obraId, FaseTransformacion $fase): ?array
    {
        if (! $this->activo() || $fase === FaseTransformacion::Primera) {
            return null;
        }

        return $this->calculadas[$obraId.'|'.$fase->value] ??= $this->calcular($obraId, $fase);
    }

    /**
     * Por qué no se puede inspeccionar esa pieza, o null si se puede.
     */
    public function motivoDeBloqueo(int $obraId, FaseTransformacion $fase, ?string $qr): ?string
    {
        $habilitadas = $this->deFase($obraId, $fase);

        if ($habilitadas === null) {
            return null;
        }

        if (! $habilitadas['plan_cerrado']) {
            return "Producción no ha cerrado el plan de {$this->nombreDeFase($fase)} de esta semana: no se puede registrar ninguna pieza de esta obra en esa fase.";
        }

        if ($qr === null || ! isset($habilitadas['qrs'][$qr])) {
            return "Esta pieza no está en el avance de producción de {$this->nombreDeFase($fase)}: no fue programada en un plan cerrado ni tiene inspecciones en esa fase.";
        }

        return null;
    }

    /**
     * Para la pestaña Registros, que enseña las tres etapas juntas: lo
     * habilitado en 2ª o en 3ª. Null si el interruptor está apagado.
     *
     * @return array<string, true>|null
     */
    public function paraRegistros(int $obraId): ?array
    {
        if (! $this->activo()) {
            return null;
        }

        return $this->deFase($obraId, FaseTransformacion::Segunda)['qrs']
            + $this->deFase($obraId, FaseTransformacion::Tercera)['qrs'];
    }

    /**
     * Lo que la pantalla necesita para avisar: la semana y si cada fase tiene
     * su plan cerrado. Null si el interruptor está apagado.
     *
     * @return array{semana: string, fases: array<string, bool>}|null
     */
    public function estado(int $obraId): ?array
    {
        if (! $this->activo()) {
            return null;
        }

        return [
            'semana' => AvanceProduccion::claveSemana(now()),
            'fases' => [
                FaseTransformacion::Segunda->value => $this->deFase($obraId, FaseTransformacion::Segunda)['plan_cerrado'],
                FaseTransformacion::Tercera->value => $this->deFase($obraId, FaseTransformacion::Tercera)['plan_cerrado'],
            ],
        ];
    }

    /**
     * @return array{plan_cerrado: bool, qrs: array<string, true>}
     */
    private function calcular(int $obraId, FaseTransformacion $fase): array
    {
        $anio = now()->isoWeekYear();
        $semana = now()->isoWeek();

        $cerrados = fn (Builder $plan): Builder => $plan
            ->where('obra_id', $obraId)
            ->where('fase', $fase->value)
            ->whereNotNull('cerrada_at');

        $planCerrado = $cerrados(Programacion::query())
            ->where(['anio' => $anio, 'semana' => $semana])
            ->exists();

        if (! $planCerrado) {
            return ['plan_cerrado' => false, 'qrs' => []];
        }

        $programadas = ProgramacionPieza::query()
            ->whereHas('programacion', fn (Builder $plan) => $cerrados($plan)
                ->where(fn (Builder $hasta) => $hasta
                    ->where('anio', '<', $anio)
                    ->orWhere(fn (Builder $mismoAnio) => $mismoAnio->where('anio', $anio)->where('semana', '<=', $semana))))
            ->pluck('qr');

        $enCurso = Inspeccion::query()
            ->where('obra_id', $obraId)
            ->where('fase', $fase->value)
            ->whereNotNull('qr')
            ->distinct()
            ->pluck('qr');

        return [
            'plan_cerrado' => true,
            'qrs' => array_fill_keys($programadas->merge($enCurso)->map(fn ($qr): string => (string) $qr)->all(), true),
        ];
    }

    private function nombreDeFase(FaseTransformacion $fase): string
    {
        return $fase === FaseTransformacion::Tercera ? 'pintura (3ª)' : 'armado y soldado (2ª)';
    }
}
