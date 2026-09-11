<?php

namespace App\Services\Qal;

use App\Enums\Qal\EstatusInspeccion;
use Illuminate\Support\Collection;

/**
 * Las cuentas del tablero de Calidad: el resumen ejecutivo, el de analítica, la
 * pestaña Operación y las tasas normalizadas que comparten.
 *
 * Son las definiciones del tablero anterior, donde ya estaban discutidas:
 *
 *  - La unidad es la PIEZA, no la inspección. El rechazo de gerencia es piezas
 *    con al menos un rechazo ÷ piezas con veredicto: una pieza rechazada tres
 *    veces es una pieza mala, no tres.
 *  - El FPY sólo mira la primera inspección de cada pieza-etapa. Mezclando las
 *    reinspecciones deja de significar «salió bien a la primera».
 *  - En armado y vestido no existe «liberado»: la pieza que sale bien queda
 *    pendiente porque avanza a soldado, así que ahí pendiente es veredicto.
 *  - Fabricación (2ª) y pintura (3ª) se reportan por separado y no se promedian.
 *  - Una tasa normalizada se calla por debajo del 30 % de cobertura y las de
 *    junta piden 20 juntas: un porcentaje sobre una junta es una anécdota.
 *  - Una pieza se agrupa por lo que traía en su PRIMERA inspección —si el
 *    retrabajo lo hizo otro soldador, sigue contando donde nació— y las
 *    tendencias van por cohorte: la semana de su primera inspección.
 *  - El RTY no se publica: multiplicaba poblaciones distintas.
 */
class TableroCalidad
{
    public const COBERTURA_MINIMA = 30;

    public const JUNTAS_MINIMAS = 20;

    public const PIEZAS_MINIMAS = 4;

    /** Una persona se juzga con más piezas: su nombre queda al lado del número. */
    public const PIEZAS_MINIMAS_PERSONA = 10;

    public const TOPE_DE_BARRAS = 12;

    /** Días sin veredicto a partir de los cuales una pieza pendiente es alerta. */
    public const DIAS_PENDIENTE = 14;

    /** Exposiciones imposibles: se sacan del denominador en vez de corregirlas. */
    private const TOPES = ['elem' => 2000, 'ton' => 20000, 'm2' => 1000];

    /** Las dimensiones de «Rechazo por», con el campo de la fila que las lleva. */
    private const DIMENSIONES_RECHAZO = [
        'obra' => 'obra',
        'soldador' => 'soldador',
        'p2_subetapa' => 'subetapa',
        'tipo' => 'tipo',
        'modulo' => 'modulo',
        'inspector' => 'inspector',
        'p1_subtipo' => 'subtipo',
    ];

    private const DIMENSIONES_TASA = ['obra', 'soldador', 'tipo', 'modulo', 'inspector'];

    private const PERSONAS = ['soldador', 'inspector'];

    public function __construct(private readonly FilasDelTablero $filas) {}

    /**
     * @param  array<string, string|null>  $filtros
     * @return array{resumen: array<string, mixed>, operacion: array<string, mixed>, tasas: array<string, array<string, mixed>>}
     */
    public function calcular(array $filtros): array
    {
        $filas = $this->filas->filas($filtros);
        $piezas = $this->porPieza($filas);
        $conVeredicto = $piezas->where('conVeredicto', true)->values();

        return [
            'resumen' => $this->resumen($filas, $piezas, $conVeredicto, $this->filas->juntas($filtros)),
            'operacion' => $this->operacion($filas, $piezas, $conVeredicto),
            'tasas' => collect(array_keys(self::TOPES))
                ->mapWithKeys(fn (string $base): array => [$base => $this->tasa($filas, $base)])
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @param  Collection<int, array<string, mixed>>  $piezas
     * @param  Collection<int, array<string, mixed>>  $conVeredicto
     * @param  Collection<int, array{clave: string, intento: int, correcta: bool}>  $juntas
     * @return array<string, mixed>
     */
    private function resumen(Collection $filas, Collection $piezas, Collection $conVeredicto, Collection $juntas): array
    {
        $juzgadas = $filas->filter(fn (array $fila): bool => $this->tieneVeredicto($fila));
        $obras = $filas->pluck('obra_id')->unique()->count();
        $lotesSinDisposicion = $filas->where('loteSinDisposicion', true);
        $limite = now()->subDays(self::DIAS_PENDIENTE)->toDateString();

        return [
            'liberadas' => $this->liberadas($filas) + ['obras' => $obras],
            'rechazo' => [
                '2ª' => $this->rechazoDeFase($conVeredicto, '2ª'),
                '3ª' => $this->rechazoDeFase($conVeredicto, '3ª'),
            ],
            'pendientes' => $piezas->count() - $conVeredicto->count(),
            'inspecciones' => [
                'total' => $filas->count(),
                'lotes' => $filas->where('lote', '>', 1)->count(),
                'miradas' => (int) $filas->sum('muestra'),
                'representadas' => (int) $filas->sum('lote'),
            ],
            'obras' => $obras,
            // Por inspección, a propósito: es el dato de control, no el de gerencia.
            'inspeccionesRechazadas' => $this->porcentaje($juzgadas->where('estatus', EstatusInspeccion::Rechazado->value)->count(), $juzgadas->count()),
            'fpy' => [
                '2ª' => $this->fpy($filas->where('fase', '2ª')),
                '3ª' => $this->fpy($filas->where('fase', '3ª')),
            ],
            'rechazoFinal' => [
                '2ª' => $this->rechazoFinal($filas->where('fase', '2ª')),
                '3ª' => $this->rechazoFinal($filas->where('fase', '3ª')),
            ],
            'enAmbas' => $filas
                ->whereNotNull('qr')
                ->groupBy(fn (array $fila): string => $fila['obra_id'].'|'.$fila['qr'])
                ->filter(fn (Collection $historia): bool => $historia->contains('fase', '2ª') && $historia->contains('fase', '3ª'))
                ->count(),
            'juntas' => $this->juntas($juntas),
            'alertas' => [
                'lotesSinDisposicion' => ['lotes' => $lotesSinDisposicion->count(), 'piezas' => (int) $lotesSinDisposicion->sum('lote')],
                'pendientesViejas' => $piezas
                    ->where('conVeredicto', false)
                    ->filter(fn (array $pieza): bool => $pieza['ultima']['fecha'] < $limite)
                    ->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @param  Collection<int, array<string, mixed>>  $piezas
     * @param  Collection<int, array<string, mixed>>  $conVeredicto
     * @return array<string, mixed>
     */
    private function operacion(Collection $filas, Collection $piezas, Collection $conVeredicto): array
    {
        return [
            'resultadoPorObra' => $this->resultadoPorObra($piezas),
            'rechazoPorFase' => collect(['1ª', '2ª', '3ª'])
                ->map(function (string $fase) use ($conVeredicto): ?array {
                    $deFase = $conVeredicto->filter(fn (array $pieza): bool => $pieza['primera']['fase'] === $fase);

                    return $deFase->isEmpty() ? null : ['fase' => $fase, 'pct' => $this->tasaDeRechazo($deFase), 'n' => $deFase->count()];
                })
                ->filter()
                ->values()
                ->all(),
            'tendencia' => [
                'week' => $this->cohortes($conVeredicto, 'semana'),
                'month' => $this->cohortes($conVeredicto, 'mes'),
            ],
            'rechazoPor' => collect(self::DIMENSIONES_RECHAZO)
                ->map(fn (string $campo, string $dimension): array => $this->rechazoPor($conVeredicto, $campo, $dimension))
                ->all(),
            // Siempre acotado por etapa: un Pareto que junta soldadura con
            // pintura ordena procesos distintos y no dice dónde actuar.
            'pareto' => [
                'p2_deftypes' => $this->pareto($filas->where('fase', '2ª')->where('armado', false), 'defectos'),
                'armado' => $this->pareto($filas->where('armado', true), 'fallasArmado'),
                'p3_deftypes' => $this->pareto($filas->where('fase', '3ª'), 'defectos'),
            ],
        ];
    }

    /**
     * Cada pieza con su historia, de la primera inspección a la última.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return Collection<int, array{primera: array<string, mixed>, ultima: array<string, mixed>, rechazos: int, conVeredicto: bool}>
     */
    private function porPieza(Collection $filas): Collection
    {
        return $filas
            ->groupBy('pieza')
            ->map(fn (Collection $historia): array => [
                'primera' => $historia->first(),
                'ultima' => $historia->last(),
                'rechazos' => $historia->where('estatus', EstatusInspeccion::Rechazado->value)->count(),
                'conVeredicto' => $historia->contains(fn (array $fila): bool => $this->tieneVeredicto($fila)),
            ])
            ->values();
    }

    /**
     * La última inspección de cada pieza-etapa: la que dice cómo acabó.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return Collection<int, array<string, mixed>>
     */
    private function ultimaPorEtapa(Collection $filas): Collection
    {
        return $filas
            ->groupBy('etapa')
            ->map(fn (Collection $historia): array => $historia->sortBy('inspeccion')->last())
            ->values();
    }

    /**
     * Liberada es que TODAS sus etapas acabaron bien. Las unidades cuentan los
     * lotes por lo que amparan; las piezas, una por pieza.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array{piezas: int, unidades: int}
     */
    private function liberadas(Collection $filas): array
    {
        $liberadas = $this->ultimaPorEtapa($filas)
            ->groupBy('pieza')
            ->filter(fn (Collection $etapas): bool => $etapas->every(fn (array $fila): bool => $this->salioBien($fila)));

        return [
            'piezas' => $liberadas->count(),
            'unidades' => (int) $liberadas->sum(fn (Collection $etapas): int => (int) $etapas->max('lote')),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $conVeredicto
     * @return array{pct: float|null, conRechazo: int, piezas: int, reprocesos: int, delta: float|null}|null
     */
    private function rechazoDeFase(Collection $conVeredicto, string $fase): ?array
    {
        $piezas = $conVeredicto->filter(fn (array $pieza): bool => $pieza['primera']['fase'] === $fase);

        if ($piezas->isEmpty()) {
            return null;
        }

        $semanas = $this->cohortes($piezas, 'semana');
        $ultimas = array_slice($semanas, -2);

        return [
            'pct' => $this->tasaDeRechazo($piezas),
            'conRechazo' => $piezas->where('rechazos', '>', 0)->count(),
            'piezas' => $piezas->count(),
            'reprocesos' => (int) $piezas->sum('rechazos'),
            // Contra la semana anterior, en puntos: la flecha de la tarjeta.
            'delta' => count($ultimas) === 2 ? round($ultimas[1]['pct'] - $ultimas[0]['pct'], 1) : null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas  de una sola fase
     * @return array{pct: float, n: int}|null
     */
    private function fpy(Collection $filas): ?array
    {
        $primeras = $filas
            ->filter(fn (array $fila): bool => $fila['inspeccion'] === 1 && $this->tieneVeredicto($fila))
            ->unique('etapa');

        if ($primeras->isEmpty()) {
            return null;
        }

        $rechazadas = $primeras->where('estatus', EstatusInspeccion::Rechazado->value)->count();

        return ['pct' => round((1 - $rechazadas / $primeras->count()) * 100, 1), 'n' => $primeras->count()];
    }

    /**
     * Distinto del % de piezas con rechazo: aquél incluye las que se repararon
     * y se liberaron; éste sólo las que acabaron rechazadas.
     *
     * @param  Collection<int, array<string, mixed>>  $filas  de una sola fase
     * @return array{pct: float|null, n: int}|null
     */
    private function rechazoFinal(Collection $filas): ?array
    {
        $ultimas = $this->ultimaPorEtapa($filas)->filter(fn (array $fila): bool => $this->tieneVeredicto($fila));

        if ($ultimas->isEmpty()) {
            return null;
        }

        return [
            'pct' => $this->porcentaje($ultimas->where('estatus', EstatusInspeccion::Rechazado->value)->count(), $ultimas->count()),
            'n' => $ultimas->count(),
        ];
    }

    /**
     * La junta es la misma junta de la misma pieza en todos sus intentos.
     *
     * @param  Collection<int, array{clave: string, intento: int, correcta: bool}>  $juntas
     * @return array{n: int, fpy: float|null, final: float|null, reproceso: float|null}|null
     */
    private function juntas(Collection $juntas): ?array
    {
        if ($juntas->isEmpty()) {
            return null;
        }

        $porJunta = $juntas->groupBy('clave')->map(fn (Collection $intentos): Collection => $intentos->sortBy('intento')->values());
        $n = $porJunta->count();

        return [
            'n' => $n,
            'fpy' => $this->porcentaje($porJunta->filter(fn (Collection $intentos): bool => $intentos->first()['correcta'])->count(), $n),
            'final' => $this->porcentaje($porJunta->filter(fn (Collection $intentos): bool => $intentos->last()['correcta'])->count(), $n),
            'reproceso' => $this->porcentaje($porJunta->filter(fn (Collection $intentos): bool => $intentos->last()['intento'] > 1)->count(), $n),
        ];
    }

    /**
     * Cómo acabó cada pieza, por obra. Las pendientes no son un fallo: son
     * trabajo sin cerrar.
     *
     * @param  Collection<int, array<string, mixed>>  $piezas
     * @return list<array{obra: string, liberadas: int, rechazadas: int, pendientes: int}>
     */
    private function resultadoPorObra(Collection $piezas): array
    {
        return $piezas
            ->groupBy(fn (array $pieza): int => $pieza['primera']['obra_id'])
            ->map(fn (Collection $deObra): array => [
                'obra' => $deObra->first()['primera']['obra'],
                'liberadas' => $deObra->filter(fn (array $pieza): bool => $this->salioBien($pieza['ultima']))->count(),
                'rechazadas' => $deObra->filter(fn (array $pieza): bool => $pieza['ultima']['estatus'] === EstatusInspeccion::Rechazado->value)->count(),
                'pendientes' => $deObra->filter(fn (array $pieza): bool => ! $this->tieneVeredicto($pieza['ultima']))->count(),
            ])
            ->sortByDesc(fn (array $obra): int => $obra['liberadas'] + $obra['rechazadas'] + $obra['pendientes'])
            ->take(self::TOPE_DE_BARRAS)
            ->values()
            ->all();
    }

    /**
     * La serie por cohorte: cada pieza cuenta en el periodo de su primera
     * inspección, no en el del retrabajo. Si no, una mala semana contamina dos.
     *
     * @param  Collection<int, array<string, mixed>>  $piezas
     * @return list<array{periodo: string, pct: float, n: int}>
     */
    private function cohortes(Collection $piezas, string $campo): array
    {
        return $piezas
            ->groupBy(fn (array $pieza): string => $pieza['primera'][$campo])
            ->sortKeys()
            ->map(fn (Collection $cohorte): array => [
                'periodo' => $cohorte->first()['primera'][$campo],
                'pct' => (float) $this->tasaDeRechazo($cohorte),
                'n' => $cohorte->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * «Rechazo por» una dimensión, con sus guardas: fuera los grupos con muy
     * pocas piezas —un 100 % sobre dos piezas no es una tasa—, tope de barras y,
     * si la lista mezcla transformaciones, la base de cada una. Sin esa base un
     * módulo de pintura al 20 % parece mejor que uno de 2ª al 50 %, cuando cada
     * transformación rechaza a su propio nivel.
     *
     * @param  Collection<int, array<string, mixed>>  $conVeredicto
     * @return array<string, mixed>
     */
    private function rechazoPor(Collection $conVeredicto, string $campo, string $dimension): array
    {
        $grupos = $conVeredicto
            ->filter(fn (array $pieza): bool => filled($pieza['primera'][$campo]))
            ->groupBy(fn (array $pieza): string => (string) $pieza['primera'][$campo])
            ->map(function (Collection $grupo) use ($campo): array {
                $fases = $grupo->pluck('primera.fase')->unique();

                return [
                    'nombre' => (string) $grupo->first()['primera'][$campo],
                    'pct' => (float) $this->tasaDeRechazo($grupo),
                    'n' => $grupo->count(),
                    'fase' => $fases->count() === 1 ? $fases->first() : 'mixta',
                ];
            })
            ->sortByDesc('pct')
            ->values();

        $minimo = in_array($dimension, self::PERSONAS, true) ? self::PIEZAS_MINIMAS_PERSONA : self::PIEZAS_MINIMAS;
        $publicables = $grupos->where('n', '>=', $minimo)->values();
        $fases = $publicables->pluck('fase')->reject(fn (string $fase): bool => $fase === 'mixta')->unique()->sort()->values();

        return [
            'filas' => $publicables->take(self::TOPE_DE_BARRAS)->all(),
            'minimo' => $minimo,
            'fuera' => $grupos->count() - $publicables->count(),
            'publicables' => $publicables->count(),
            'cubiertas' => (int) $publicables->sum('n'),
            'total' => $conVeredicto->count(),
            'bases' => $fases->count() < 2 ? [] : $fases
                ->map(fn (string $fase): array => [
                    'fase' => $fase,
                    'pct' => (float) $this->tasaDeRechazo($conVeredicto->filter(fn (array $pieza): bool => $pieza['primera']['fase'] === $fase)),
                ])
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return list<array{causa: string, n: int}>
     */
    private function pareto(Collection $filas, string $campo): array
    {
        $cuenta = [];

        foreach ($filas as $fila) {
            foreach ($fila[$campo] as $causa => $veces) {
                $cuenta[$causa] = ($cuenta[$causa] ?? 0) + $veces;
            }
        }

        arsort($cuenta);

        return collect($cuenta)
            ->map(fn (int $veces, int|string $causa): array => ['causa' => (string) $causa, 'n' => $veces])
            ->values()
            ->all();
    }

    /**
     * Una tasa de defectos normalizada: el total, su cobertura y el desglose
     * por dimensión.
     *
     * El denominador es FÍSICO y se cuenta una vez por pieza aunque se haya
     * reinspeccionado; el numerador son los defectos de su primera inspección.
     * En 2ª sólo cuenta soldado, que es donde se capturan los defectos de
     * soldadura y el número de elementos: las toneladas de una pieza que sólo
     * pasó por armado entrarían al denominador sin poder aportar un defecto.
     * En un lote con muestreo la exposición es la muestra, no el lote.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function tasa(Collection $filas, string $base): array
    {
        $candidatas = $base === 'm2'
            ? $filas->where('fase', '3ª')
            : $filas->where('fase', '2ª')->where('armado', false);

        $porPieza = $candidatas->groupBy('pieza')->map(function (Collection $historia) use ($base): ?array {
            $exposicion = $this->exposicion($historia->first(), $base) ?? $this->exposicion($historia->last(), $base);

            return $exposicion === null ? null : [
                'q' => $exposicion,
                'd' => array_sum($historia->first()['defectos']),
                'fila' => $historia->first(),
            ];
        });
        $medidas = $porPieza->filter()->values();
        $q = (float) $medidas->sum('q');
        $d = (int) $medidas->sum('d');

        return [
            'tasa' => $q > 0 ? round($d / $q, 4) : null,
            'defectos' => $d,
            'exposicion' => round($q, 2),
            'cobertura' => [
                'pct' => $this->porcentaje($medidas->count(), $porPieza->count()),
                'con' => $medidas->count(),
                'total' => $porPieza->count(),
                // Capturas con una medida imposible: se dicen para que alguien
                // corrija el registro, no se corrigen aquí.
                'fuera' => $candidatas->filter(fn (array $fila): bool => ($this->medida($fila, $base) ?? 0) > self::TOPES[$base])->count(),
            ],
            'porDimension' => collect(self::DIMENSIONES_TASA)
                ->mapWithKeys(fn (string $dimension): array => [$dimension => $medidas
                    ->filter(fn (array $pieza): bool => filled($pieza['fila'][$dimension]))
                    ->groupBy(fn (array $pieza): string => (string) $pieza['fila'][$dimension])
                    ->map(fn (Collection $grupo): array => [
                        'nombre' => (string) $grupo->first()['fila'][$dimension],
                        'tasa' => round($grupo->sum('d') / $grupo->sum('q'), 4),
                        'exposicion' => round((float) $grupo->sum('q'), 2),
                    ])
                    ->sortByDesc('tasa')
                    ->take(self::TOPE_DE_BARRAS)
                    ->values()
                    ->all()])
                ->all(),
        ];
    }

    /** @param  array<string, mixed>  $fila */
    private function medida(array $fila, string $base): ?float
    {
        return match ($base) {
            'elem' => $fila['elementos'],
            'ton' => $fila['kg'],
            default => $fila['area'],
        };
    }

    /**
     * La exposición inspeccionada de una fila, o nula si no se capturó o es
     * imposible.
     *
     * @param  array<string, mixed>  $fila
     */
    private function exposicion(array $fila, string $base): ?float
    {
        $medida = $this->medida($fila, $base);

        if ($medida === null || $medida <= 0 || $medida > self::TOPES[$base]) {
            return null;
        }

        return ($base === 'ton' ? $medida / 1000 : $medida) * $fila['muestra'];
    }

    /** @param  array<string, mixed>  $fila */
    private function tieneVeredicto(array $fila): bool
    {
        return $fila['armado'] || $fila['estatus'] !== EstatusInspeccion::Pendiente->value;
    }

    /** @param  array<string, mixed>  $fila */
    private function salioBien(array $fila): bool
    {
        return $fila['estatus'] === EstatusInspeccion::Liberado->value
            || ($fila['armado'] && $fila['estatus'] === EstatusInspeccion::Pendiente->value);
    }

    /** @param  Collection<int, array<string, mixed>>  $piezas */
    private function tasaDeRechazo(Collection $piezas): ?float
    {
        return $this->porcentaje($piezas->where('rechazos', '>', 0)->count(), $piezas->count());
    }

    /** Con un decimal, o nulo cuando no hay base. */
    private function porcentaje(int $parte, int $total): ?float
    {
        return $total > 0 ? round($parte * 100 / $total, 1) : null;
    }
}
