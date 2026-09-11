<?php

namespace App\Services\Qal\Formatos;

use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Models\Prod\Pieza;
use App\Models\Qal\Junta;
use App\Models\Qal\JuntaPunto;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * F-STX-CA-04 · Reporte de mapeo: la inspección visual junta por junta de UNA
 * pieza, con los 18 puntos en el orden del formato.
 *
 * De cada junta cuenta su último intento, sea en la inspección que sea: la
 * junta reparada en la segunda inspección de la pieza sustituye a la de la
 * primera. La hoja isométrica sigue saliendo del visor; esto es la tabla.
 */
class MapeoDeJuntas extends Formato
{
    public const PUNTOS = [
        'm_material' => 'Material correcto',
        'm_prepfilete' => 'Prep. junta filete',
        'm_prepranura' => 'Prep. junta ranura',
        'm_respaldo' => 'Placa de respaldo',
        'm_acceso' => 'Radios de acceso',
        'm_corte' => 'Corte sin muescas',
        'm_precal' => 'Precalentamiento',
        'm_limpieza' => 'Limpieza entre pasadas',
        'm_grieta' => 'Grieta',
        'm_fusion' => 'Falta de fusión',
        'm_traslape' => 'Traslape',
        'm_insuf' => 'Soldadura insuficiente',
        'm_poros' => 'Porosidad',
        'm_socav' => 'Socavado',
        'm_perfil' => 'Perfil de soldadura',
        'm_crater' => 'Cráter',
        'm_retiro' => 'Retiro de puntos',
        'm_matbase' => 'Material base dañado',
    ];

    public function clave(): string
    {
        return 'mapeo';
    }

    public function codigo(): string
    {
        return 'F-STX-CA-04';
    }

    public function revision(): string
    {
        return 'Revisión 01';
    }

    public function titulo(): string
    {
        return 'Inspección visual de soldadura';
    }

    public function subtitulo(): string
    {
        return 'Reporte de mapeo · Steelex Estructuras Metálicas';
    }

    public function destino(): string
    {
        return self::DOSIER;
    }

    public function fase(): FaseTransformacion
    {
        return FaseTransformacion::Segunda;
    }

    public function subetapa(): ?Subetapa
    {
        return Subetapa::Soldado;
    }

    public function usaPeriodo(): bool
    {
        return false;
    }

    public function vista(): string
    {
        return 'mapeo';
    }

    public function datos(FiltrosDeReporte $filtros): array
    {
        $pieza = $filtros->piezaId ? Pieza::query()->with('marca:id,marca')->find($filtros->piezaId) : null;

        $juntas = $pieza ? Junta::query()
            ->whereHas('inspeccion', fn (Builder $inspeccion) => $inspeccion
                ->where('obra_id', $filtros->obraId)
                ->where('prod_pieza_id', $pieza->id)
                ->where('subetapa', Subetapa::Soldado->value))
            ->with(['puntos.punto:id,clave', 'soldador:id,nombre,clave', 'inspeccion:id,fecha,linea,numero_inspeccion,inspector_id', 'inspeccion.inspector:id,usuario_id'])
            ->get()
            ->groupBy('identificador')
            ->map(fn ($intentos) => $intentos->sortBy(fn (Junta $junta): array => [$junta->inspeccion->numero_inspeccion, $junta->intento, $junta->id])->last())
            ->sortKeysUsing(fn (string $a, string $b): int => strnatcasecmp($a, $b))
            ->values() : collect();

        $ultima = $juntas
            ->map(fn (Junta $junta) => $junta->inspeccion)
            ->sortBy(fn ($inspeccion): array => [$inspeccion->fecha->toDateString(), $inspeccion->numero_inspeccion])
            ->last();

        return [
            'generales' => [
                'Obra' => $this->obra($filtros->obraId),
                'Marca' => $pieza?->marca?->marca ?? '',
                'QR' => $pieza?->qr ?? '',
                'Línea' => $ultima?->linea ?? '',
                'Fecha' => $ultima ? CarbonImmutable::parse($ultima->fecha)->format('d/m/Y') : '',
                'Norma' => 'AWS D1.1',
            ],
            'puntos' => array_values(self::PUNTOS),
            'renglones' => $juntas->map(function (Junta $junta): array {
                $respuestas = $junta->puntos->mapWithKeys(fn (JuntaPunto $punto): array => [$punto->punto->clave => $punto->resultado?->value]);

                return [
                    'junta' => $junta->identificador,
                    'soldador' => $junta->soldador?->clave ?: $junta->soldador?->nombre,
                    'tipo' => $junta->tipo->etiqueta(),
                    'celdas' => array_map(
                        fn (string $clave): array => Celda::punto($respuestas->has($clave) ? ['resultado' => $respuestas[$clave], 'valor' => null] : null),
                        array_keys(self::PUNTOS),
                    ),
                ];
            })->all(),
            'vacio' => match (true) {
                $pieza === null => 'Elige la pieza: el mapeo es de una pieza.',
                $juntas->isEmpty() => 'No hay juntas registradas para esta pieza.',
                default => null,
            },
            'nota' => null,
            'leyenda' => 'A = aceptable · D = defecto · N/A = no aplica · — = sin registro. De cada junta cuenta su último intento.',
            'creador_id' => $ultima?->inspector?->usuario_id,
        ];
    }
}
