<?php

namespace App\Services\Qal;

use App\Enums\Qal\EstatusInspeccion;
use Illuminate\Support\Collection;

/**
 * La pestaña Estadística del tablero: ¿es real?
 *
 * Las demás pestañas cuentan lo que pasó; ésta dice si una diferencia es señal
 * o es ruido. Reglas que vienen del tablero anterior, donde se discutieron:
 *
 *  - La carta-p cuenta inspecciones por semana y sus límites se mueven con el
 *    tamaño de cada semana: una de 12 tolera más variación que una de 90.
 *  - El Cpk va por espesor requerido, nunca global: cada proyecto pinta con un
 *    sistema distinto y mezclar 16 mils con 3 no describe a ninguno.
 *  - El espesor se compara en margen sobre el mínimo de SU proyecto, no en mils.
 *  - El chi-cuadrado mira el veredicto final de cada pieza-etapa y compara
 *    dentro de una etapa: 2ª y pintura rechazan a niveles muy distintos, y
 *    juntarlas vuelve «significativo» a cualquier factor repartido desigual.
 *  - La tendencia es la de Cochran-Armitage: si la proporción sube o baja de
 *    forma sostenida, no si dos semanas sueltas se diferencian.
 */
class EstadisticaDelTablero
{
    use ReglasDeVeredicto;

    /** Semanas a partir de las cuales los límites de la carta-p dejan de ser preliminares. */
    public const SEMANAS_CARTA = 20;

    /** Piezas con espesor medido que pide el histograma del margen. */
    public const PIEZAS_MARGEN = 3;

    /** Piezas de una categoría para entrar a la prueba: menos, no empuja conclusiones. */
    public const PIEZAS_POR_CATEGORIA = 5;

    public const PIEZAS_PRUEBA = 20;

    public const SEMANAS_TENDENCIA = 3;

    /** Los factores contra los que se prueba el rechazo, con el campo de la fila. */
    private const FACTORES = [
        'p2_subetapa' => 'subetapa',
        'tipo' => 'tipo',
        'soldador' => 'soldador',
        'obra' => 'obra',
        'inspector' => 'inspector',
    ];

    /** Por encima del tope el valor es un error de captura y se descarta. */
    private const TOPES = ['peso' => 20000, 'elementos' => 2000, 'area' => 1000];

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    public function calcular(Collection $filas): array
    {
        $juzgadas = $filas->filter(fn (array $fila): bool => $this->tieneVeredicto($fila));
        $finales = $this->ultimaPorEtapa($juzgadas);
        $pintura = $filas->where('fase', '3ª');
        $piezasPintura = $pintura->pluck('pieza')->unique()->count();
        $medidas = $pintura
            ->filter(fn (array $fila): bool => $fila['espesor'] !== null && $fila['requerido'] > 0)
            ->keyBy('pieza')
            ->values();

        return [
            'cartaP' => $this->cartaP($juzgadas),
            'margen' => $this->margen($medidas, $piezasPintura),
            'capacidad' => $this->capacidad($medidas),
            'coberturaEspesor' => [
                'con' => $pintura->whereNotNull('espesor')->pluck('pieza')->unique()->count(),
                'total' => $piezasPintura,
            ],
            'descriptiva' => $this->descriptiva($filas),
            'pruebas' => collect(['2ª', '3ª'])
                ->mapWithKeys(fn (string $fase): array => [$fase => collect(self::FACTORES)
                    ->map(fn (string $campo): array => $this->chiCuadrado($finales->where('fase', $fase), $campo))
                    ->all()])
                ->all(),
            'tendencias' => collect(['2ª', '3ª'])
                ->mapWithKeys(fn (string $fase): array => [$fase => $this->tendencia($finales->where('fase', $fase))])
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $juzgadas
     * @return array<string, mixed>|null
     */
    private function cartaP(Collection $juzgadas): ?array
    {
        $semanas = $juzgadas->groupBy('semana')->sortKeys();

        if ($semanas->count() < 2) {
            return null;
        }

        $pbar = $this->rechazadas($juzgadas) / $juzgadas->count();

        return [
            'preliminar' => $semanas->count() < self::SEMANAS_CARTA,
            'subgrupos' => $semanas->count(),
            'pbar' => round($pbar * 100, 1),
            'puntos' => $semanas
                ->map(function (Collection $semana) use ($pbar): array {
                    $tamano = $semana->count();
                    $error = sqrt($pbar * (1 - $pbar) / $tamano);

                    return [
                        'semana' => $semana->first()['semana'],
                        'p' => round($this->rechazadas($semana) * 100 / $tamano, 1),
                        'ucl' => round(min(1, $pbar + 3 * $error) * 100, 1),
                        'lcl' => round(max(0, $pbar - 3 * $error) * 100, 1),
                        'n' => $tamano,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * Cuánto se pasa cada pieza de su mínimo, en %. El cero es el mínimo del
     * proyecto; a su izquierda, pieza fuera de norma.
     *
     * @param  Collection<int, array<string, mixed>>  $medidas  la última medición de cada pieza
     * @return array<string, mixed>
     */
    private function margen(Collection $medidas, int $piezasPintura): array
    {
        $base = ['piezas' => $medidas->count(), 'piezasPintura' => $piezasPintura];

        if ($medidas->count() < self::PIEZAS_MARGEN) {
            return $base + ['resumen' => null, 'bins' => []];
        }

        $porcentajes = $medidas->map(fn (array $fila): float => ($fila['espesor'] - $fila['requerido']) / $fila['requerido'] * 100)->all();
        $enPorcentaje = Estadistica::describir($porcentajes);
        $enMils = Estadistica::describir($medidas->map(fn (array $fila): float => $fila['espesor'] - $fila['requerido'])->all());

        $cubetas = min(10, max(5, (int) ceil(sqrt(count($porcentajes)))));
        $ancho = ($enPorcentaje['max'] - $enPorcentaje['min']) / $cubetas ?: 1;
        $conteo = array_fill(0, $cubetas, 0);

        foreach ($porcentajes as $valor) {
            $conteo[min($cubetas - 1, (int) floor(($valor - $enPorcentaje['min']) / $ancho))]++;
        }

        return $base + [
            'resumen' => [
                'medianaPct' => round($enPorcentaje['mediana'], 1),
                'medianaMils' => round($enMils['mediana'], 2),
                'minPct' => round($enPorcentaje['min'], 1),
                'maxPct' => round($enPorcentaje['max'], 1),
                'bajoMinimo' => count(array_filter($porcentajes, fn (float $valor): bool => $valor < 0)),
            ],
            'bins' => collect($conteo)
                ->map(fn (int $piezas, int $cubeta): array => ['desde' => round($enPorcentaje['min'] + $cubeta * $ancho, 1), 'n' => $piezas])
                ->all(),
        ];
    }

    /**
     * Cp y Cpk sólo tienen límite inferior —el mínimo del proyecto—, así que
     * Cpk = (media − mínimo) ÷ 3σ. Sin variación entre piezas no hay Cpk.
     *
     * @param  Collection<int, array<string, mixed>>  $medidas
     * @return list<array<string, mixed>>
     */
    private function capacidad(Collection $medidas): array
    {
        return $medidas
            ->groupBy(fn (array $fila): string => (string) $fila['requerido'])
            ->map(function (Collection $grupo): array {
                $requerido = (float) $grupo->first()['requerido'];
                $descripcion = Estadistica::describir($grupo->pluck('espesor')->all());

                return [
                    'requerido' => $requerido,
                    'obras' => $grupo->pluck('obra')->unique()->sort()->values()->all(),
                    'n' => $descripcion['n'],
                    'media' => round($descripcion['media'], 2),
                    'sigma' => round($descripcion['sigma'], 2),
                    'cpk' => $descripcion['sigma'] > 0 ? round(($descripcion['media'] - $requerido) / (3 * $descripcion['sigma']), 2) : null,
                    'fuera' => $grupo->filter(fn (array $fila): bool => $fila['espesor'] < $requerido)->count(),
                ];
            })
            ->sortByDesc('n')
            ->values()
            ->all();
    }

    /**
     * Cómo es la pieza típica y cuánto se parecen entre sí. Cuenta piezas —la
     * última medición de cada una—, no registros, y dice sobre cuántas falta
     * el dato: «n = 32» sin saber que las piezas son 191 engaña.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function descriptiva(Collection $filas): array
    {
        $soldado = $filas->where('fase', '2ª')->where('armado', false);
        $pintura = $filas->where('fase', '3ª');
        $variables = [
            'peso' => [$filas, fn (array $fila): ?float => $fila['kg']],
            'elementos' => [$soldado, fn (array $fila): ?float => $fila['elementos']],
            'defectosSoldadura' => [$soldado, fn (array $fila): float => (float) array_sum($fila['defectos'])],
            'espesor' => [$pintura, fn (array $fila): ?float => $fila['espesor']],
            'margen' => [$pintura, fn (array $fila): ?float => $fila['espesor'] !== null && $fila['requerido'] > 0
                ? ($fila['espesor'] - $fila['requerido']) / $fila['requerido'] * 100
                : null],
            'area' => [$pintura, fn (array $fila): ?float => $fila['area']],
        ];

        return collect($variables)
            ->map(function (array $variable, string $clave): array {
                [$universo, $valorDe] = $variable;
                $tope = self::TOPES[$clave] ?? null;
                $porEtapa = [];
                $descartados = 0;

                foreach ($universo as $fila) {
                    $valor = $valorDe($fila);

                    if ($valor === null || ($tope !== null && $valor <= 0)) {
                        continue;
                    }

                    if ($tope !== null && $valor > $tope) {
                        $descartados++;

                        continue;
                    }

                    $porEtapa[$fila['etapa']] = $valor;
                }

                $descripcion = Estadistica::describir(array_values($porEtapa));

                return [
                    'clave' => $clave,
                    'conDato' => count($porEtapa),
                    'universo' => $universo->pluck('etapa')->unique()->count(),
                    'descartados' => $descartados,
                    'estadistica' => $descripcion === null ? null : [
                        'media' => round($descripcion['media'], 3),
                        'mediana' => round($descripcion['mediana'], 3),
                        'sigma' => round($descripcion['sigma'], 3),
                        'cv' => $descripcion['cv'] !== null ? round($descripcion['cv'], 1) : null,
                        'min' => round($descripcion['min'], 3),
                        'max' => round($descripcion['max'], 3),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * ¿El veredicto final depende de este factor? Las categorías con muy pocas
     * piezas se apartan: un soldador con una pieza rechazada no puede empujar
     * una conclusión. Va la V de Cramér, porque con muchos datos una diferencia
     * mínima sale significativa: la V dice si además es importante.
     *
     * @param  Collection<int, array<string, mixed>>  $finales  la última inspección de cada pieza-etapa
     * @return array<string, mixed>
     */
    private function chiCuadrado(Collection $finales, string $campo): array
    {
        $categorias = $finales
            ->filter(fn (array $fila): bool => filled($fila[$campo]))
            ->groupBy(fn (array $fila): string => (string) $fila[$campo]);
        $chicas = $categorias->filter(fn (Collection $categoria): bool => $categoria->count() < self::PIEZAS_POR_CATEGORIA);
        $grandes = $categorias->diffKeys($chicas);
        $total = (int) $grandes->sum(fn (Collection $categoria): int => $categoria->count());
        $rechazadas = (int) $grandes->sum(fn (Collection $categoria): int => $this->rechazadas($categoria));

        if ($grandes->count() < 2 || $total < self::PIEZAS_PRUEBA) {
            return ['estado' => 'sin_muestra', 'piezas' => $total, 'fuera' => $chicas->count()];
        }

        if ($rechazadas === 0) {
            return ['estado' => 'sin_rechazos', 'piezas' => $total, 'fuera' => $chicas->count()];
        }

        $base = $rechazadas / $total;
        $chi2 = 0.0;
        $celdasBajas = 0;

        $detalle = $grandes
            ->map(function (Collection $categoria) use ($base, $campo, &$chi2, &$celdasBajas): array {
                $piezas = $categoria->count();
                $rechazadas = $this->rechazadas($categoria);
                $esperadas = $piezas * $base;

                foreach ([[$rechazadas, $esperadas], [$piezas - $rechazadas, $piezas - $esperadas]] as [$observadas, $esperadasCelda]) {
                    if ($esperadasCelda > 0) {
                        $chi2 += ($observadas - $esperadasCelda) ** 2 / $esperadasCelda;
                        $celdasBajas += $esperadasCelda < 5 ? 1 : 0;
                    }
                }

                return [
                    'categoria' => (string) $categoria->first()[$campo],
                    'n' => $piezas,
                    'rechazadas' => $rechazadas,
                    'pct' => round($rechazadas * 100 / $piezas, 1),
                    'esperadas' => round($esperadas, 2),
                    'z' => $esperadas > 0 ? round(($rechazadas - $esperadas) / sqrt($esperadas), 2) : 0.0,
                ];
            })
            ->sortByDesc('pct')
            ->values()
            ->all();

        $gl = $grandes->count() - 1;

        return [
            'estado' => 'ok',
            'chi2' => round($chi2, 2),
            'gl' => $gl,
            'p' => round(Estadistica::pChiCuadrado($chi2, $gl), 6),
            'v' => round(sqrt($chi2 / $total), 2),
            'piezas' => $total,
            'base' => round($base * 100, 1),
            'fuera' => $chicas->count(),
            'celdasBajas' => $celdasBajas,
            'detalle' => $detalle,
        ];
    }

    /**
     * La prueba de tendencia de Cochran-Armitage sobre el veredicto final,
     * semana a semana.
     *
     * @param  Collection<int, array<string, mixed>>  $finales
     * @return array<string, mixed>
     */
    private function tendencia(Collection $finales): array
    {
        $semanas = $finales
            ->groupBy('semana')
            ->sortKeys()
            ->map(fn (Collection $semana): array => [
                'semana' => $semana->first()['semana'],
                'n' => $semana->count(),
                'rechazadas' => $this->rechazadas($semana),
                'pct' => round($this->rechazadas($semana) * 100 / $semana->count(), 1),
            ])
            ->values();
        $base = ['semanas' => $semanas->count(), 'puntos' => $semanas->all()];
        $total = (int) $semanas->sum('n');
        $rechazadas = (int) $semanas->sum('rechazadas');

        if ($semanas->count() < self::SEMANAS_TENDENCIA) {
            return ['estado' => 'pocas_semanas'] + $base;
        }

        if ($rechazadas === 0 || $rechazadas === $total) {
            return ['estado' => 'plano'] + $base;
        }

        $proporcion = $rechazadas / $total;
        $centro = $semanas->reduce(fn (float $suma, array $semana, int $orden): float => $suma + ($orden + 1) * $semana['n'], 0.0) / $total;
        $numerador = 0.0;
        $denominador = 0.0;

        foreach ($semanas as $orden => $semana) {
            $distancia = $orden + 1 - $centro;
            $numerador += $distancia * $semana['rechazadas'];
            $denominador += $semana['n'] * $distancia ** 2;
        }

        $z = $numerador / sqrt($proporcion * (1 - $proporcion) * $denominador);

        return [
            'estado' => 'ok',
            'z' => round($z, 2),
            'p' => round(2 * (1 - Estadistica::normalAcumulada(abs($z))), 6),
        ] + $base;
    }

    /** @param  Collection<int, array<string, mixed>>  $filas */
    private function rechazadas(Collection $filas): int
    {
        return $filas->where('estatus', EstatusInspeccion::Rechazado->value)->count();
    }
}
