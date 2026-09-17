<?php

namespace App\Services\Qal;

use App\Enums\Qal\AmbitoPunto;
use App\Enums\Qal\ResultadoPunto;
use App\Enums\Qal\TipoDatoPunto;
use App\Models\Qal\PuntoInspeccion;
use Illuminate\Support\Collection;

/**
 * La pestaña Diagnóstico del tablero: ¿el dato sirve?
 *
 * Las demás pestañas miden el taller; ésta mide la captura y dónde mirar
 * primero. Reglas que vienen del tablero anterior, donde se discutieron:
 *
 *  - Una casilla en blanco no cuenta como OK: las piezas rechazadas dejan más
 *    blancos que las liberadas. El blanco no entra en ningún denominador, y el
 *    «no aplica» tampoco entra en el de defecto: diluiría la señal.
 *  - Los cortes del mapa de riesgo son absolutos sobre el total, y si el peso
 *    o el área no llegan al 50 % de cobertura el impacto se mide en piezas: un
 *    impacto 0 por falta de dato se leería como «no impacta».
 *  - Cada categoría se compara con SU etapa: en pintura casi no se rechaza, y
 *    mezclarlas hacía que cualquiera de 2ª pareciera un problema. Como se miran
 *    muchas a la vez, «confirmado» exige el listón de Bonferroni.
 *  - El defecto característico pide 3 casos y nunca es «Otro», el cajón de
 *    sastre: su rareza dispara el índice sin decir nada.
 *  - Asociación, no causa: nadie controló qué piezas le tocaron a cada quien.
 */
class DiagnosticoDelTablero
{
    use ReglasDeVeredicto;

    public const PIEZAS_MINIMAS = 4;

    /** Una persona se juzga con más piezas: su nombre queda al lado del número. */
    public const PIEZAS_MINIMAS_PERSONA = 10;

    public const CASOS_CARACTERISTICO = 3;

    /** Cobertura del peso o el área por debajo de la cual el impacto va en piezas. */
    public const COBERTURA_MAGNITUD = 50;

    public const DEFECTOS_EN_MAPA = 5;

    public const TOPE_FACTORES = 14;

    private const GENERICOS = '/^(otro|otros|n\/a|na|sin clasificar)$/i';

    private const PERSONAS = ['soldador', 'inspector'];

    /** Los factores de riesgo, con el campo de la fila que los lleva. */
    private const FACTORES = [
        'obra' => 'Obra',
        'soldador' => 'Soldador',
        'subetapa' => 'Sub-etapa',
        'tipo' => 'Tipo de pieza',
        'subtipo' => 'Perfil/Placa',
        'modulo' => 'Módulo',
        'inspector' => 'Inspector',
    ];

    private const DIMENSIONES_PERFIL = ['soldador', 'inspector', 'obra', 'modulo', 'tipo'];

    public function __construct(private readonly FilasDelTablero $filas) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @param  array<string, string|null>  $filtros
     * @return array<string, mixed>
     */
    public function calcular(Collection $filas, array $filtros): array
    {
        return [
            'usoCampos' => $this->usoCampos($filas, $this->filas->respuestas($filtros)),
            'mapas' => [
                $this->mapaDeRiesgo($filas->where('fase', '2ª'), '2ª'),
                $this->mapaDeRiesgo($filas->where('fase', '3ª'), '3ª'),
            ],
            'factores' => $this->factores($filas),
            'perfil' => collect(self::DIMENSIONES_PERFIL)
                ->mapWithKeys(fn (string $dimension): array => [$dimension => $this->perfil($filas, $dimension)])
                ->all(),
        ];
    }

    /**
     * Qué se responde y qué no, punto por punto. Sólo los puntos cuya respuesta
     * dice si hubo defecto; los que sólo describen —el tipo de desviación— o
     * se calculan solos no se le preguntan a nadie.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @param  Collection<int, array{punto_id: int, resultado: string, n: int}>  $respuestas
     * @return list<array<string, mixed>>
     */
    private function usoCampos(Collection $filas, Collection $respuestas): array
    {
        $porPunto = $respuestas->groupBy('punto_id');
        $porContexto = $filas->countBy(fn (array $fila): string => $fila['fase'].'|'.$fila['subetapa'].'|'.$fila['subtipo']);

        return PuntoInspeccion::query()
            ->where('activo', true)
            ->where('ambito', AmbitoPunto::Pieza->value)
            ->where('calculado', false)
            ->orderBy('orden')
            ->get()
            ->filter(fn (PuntoInspeccion $punto): bool => $punto->tipo_dato === TipoDatoPunto::Contador
                || ($punto->tipo_dato === TipoDatoPunto::Seleccion
                    && collect($punto->opciones ?? [])->contains(fn (array $opcion): bool => ($opcion['resultado'] ?? null) !== null)))
            ->map(function (PuntoInspeccion $punto) use ($porPunto, $porContexto): array {
                $universo = (int) $porContexto
                    ->filter(function (int $inspecciones, string $contexto) use ($punto): bool {
                        [$fase, $subetapa, $subtipo] = explode('|', $contexto);

                        return $fase === $punto->fase->value
                            && ($punto->subetapa === null || $punto->subetapa->etiqueta() === $subetapa)
                            && ($punto->subtipo === null || $punto->subtipo->etiqueta() === $subtipo);
                    })
                    ->sum();
                $cuenta = $porPunto->get($punto->id, collect())->pluck('n', 'resultado');
                $ok = (int) ($cuenta[ResultadoPunto::Ok->value] ?? 0);
                $defecto = (int) ($cuenta[ResultadoPunto::NoOk->value] ?? 0);
                $noAplica = (int) ($cuenta[ResultadoPunto::NoAplica->value] ?? 0);

                return [
                    'fase' => $punto->fase->value,
                    'bloque' => (string) $punto->seccion,
                    'campo' => (string) $punto->etiqueta,
                    'n' => $universo,
                    'ok' => $ok,
                    'defecto' => $defecto,
                    'noAplica' => $noAplica,
                    'vacio' => max(0, $universo - $ok - $defecto - $noAplica),
                ];
            })
            ->filter(fn (array $campo): bool => $campo['n'] > 0)
            ->values()
            ->all();
    }

    /**
     * Frecuencia contra impacto de los defectos más comunes de una etapa. El
     * impacto se mide en kilos (2ª) o m² (pintura) cuando hay dato para ello;
     * si no, en piezas afectadas.
     *
     * @param  Collection<int, array<string, mixed>>  $filas  de una sola etapa
     * @return array<string, mixed>
     */
    private function mapaDeRiesgo(Collection $filas, string $fase): array
    {
        $conteo = $this->contarDefectos($filas);
        $total = array_sum($conteo);
        $tope = $fase === '3ª' ? 1000 : 20000;
        $magnitud = function (array $fila) use ($fase, $tope): ?float {
            $valor = $fase === '3ª' ? $fila['area'] : $fila['kg'];

            return $valor !== null && $valor > 0 && $valor <= $tope ? (float) $valor : null;
        };

        $conDefecto = $filas->filter(fn (array $fila): bool => $this->defectosDe($fila) !== []);
        $conMagnitud = $conDefecto->filter(fn (array $fila): bool => $magnitud($fila) !== null)->count();
        $cobertura = $conDefecto->isEmpty() ? 0 : $conMagnitud * 100 / $conDefecto->count();
        $magnitudTotal = (float) $conDefecto->sum(fn (array $fila): float => $magnitud($fila) ?? 0.0);
        $usaMagnitud = $magnitudTotal > 0 && $cobertura >= self::COBERTURA_MAGNITUD;
        $piezasEtapa = $filas->pluck('pieza')->unique()->count();

        $items = [];

        foreach (array_slice($conteo, 0, self::DEFECTOS_EN_MAPA, true) as $defecto => $veces) {
            $con = $filas->filter(fn (array $fila): bool => array_key_exists($defecto, $this->defectosDe($fila)));
            $comprometida = (float) $con->sum(fn (array $fila): float => $magnitud($fila) ?? 0.0);
            $piezas = $con->pluck('pieza')->unique()->count();

            $items[] = [
                'defecto' => (string) $defecto,
                'defectos' => $veces,
                'frecuencia' => round($veces * 100 / $total, 1),
                'piezas' => $piezas,
                'magnitud' => round($comprometida, 1),
                // No suman 100: una pieza con tres defectos aporta su peso a los tres.
                'impacto' => (int) round($usaMagnitud
                    ? $comprometida * 100 / $magnitudTotal
                    : ($piezasEtapa > 0 ? $piezas * 100 / $piezasEtapa : 0)),
            ];
        }

        return [
            'fase' => $fase,
            'totalDefectos' => $total,
            'tiposDefecto' => count($conteo),
            'piezasEtapa' => $piezasEtapa,
            'usaMagnitud' => $usaMagnitud,
            'coberturaMagnitud' => (int) round($cobertura),
            'items' => $items,
        ];
    }

    /**
     * Todas las categorías de todos los factores, ordenadas por cuánto más (o
     * menos) rechazan que su etapa. Aquí una pieza cuenta como rechazada si lo
     * fue alguna vez, igual que en la portada.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function factores(Collection $filas): array
    {
        $senales = [];

        foreach (['1ª', '2ª', '3ª'] as $fase) {
            $piezas = $this->porPieza($filas->where('fase', $fase))->where('conVeredicto', true);
            $base = $piezas->isEmpty() ? 0.0 : $piezas->where('rechazos', '>', 0)->count() / $piezas->count();

            if ($base <= 0) {
                continue;
            }

            $candidatas = [];

            foreach (self::FACTORES as $campo => $factor) {
                $minimo = in_array($campo, self::PERSONAS, true) ? self::PIEZAS_MINIMAS_PERSONA : self::PIEZAS_MINIMAS;
                $grupos = $piezas
                    ->filter(fn (array $pieza): bool => filled($pieza['primera'][$campo]))
                    ->groupBy(fn (array $pieza): string => (string) $pieza['primera'][$campo])
                    ->filter(fn (Collection $grupo): bool => $grupo->count() >= $minimo);

                foreach ($grupos as $grupo) {
                    $n = $grupo->count();
                    $rechazadas = $grupo->where('rechazos', '>', 0)->count();

                    $candidatas[] = [
                        'fase' => $fase,
                        'factor' => $factor,
                        'valor' => (string) $grupo->first()['primera'][$campo],
                        'piezas' => $n,
                        'rechazadas' => $rechazadas,
                        'tasa' => round($rechazadas * 100 / $n, 1),
                        'base' => round($base * 100, 1),
                        'rr' => ($rechazadas / $n) / $base,
                        'p' => Estadistica::pBinomialDosColas($rechazadas, $n, $base),
                    ];
                }
            }

            $limite = 0.05 / max(1, count($candidatas));

            foreach ($candidatas as $candidata) {
                if ($candidata['rr'] >= 1.3 || $candidata['rr'] <= 0.7) {
                    $senales[] = $candidata + [
                        'evidencia' => $candidata['p'] < $limite ? 'confirmado' : ($candidata['p'] < 0.05 ? 'indicio' : 'nada'),
                    ];
                }
            }
        }

        $orden = ['confirmado' => 0, 'indicio' => 1, 'nada' => 2];

        return collect($senales)
            ->sort(fn (array $a, array $b): int => [$orden[$a['evidencia']], -$a['rr']] <=> [$orden[$b['evidencia']], -$b['rr']])
            ->take(self::TOPE_FACTORES)
            ->map(fn (array $senal): array => ['rr' => round($senal['rr'], 2), 'p' => round($senal['p'], 6)] + $senal)
            ->values()
            ->all();
    }

    /**
     * Qué le sale mal a cada uno, dentro de su etapa. No mide quién rechaza
     * más: dos soldadores con el mismo rechazo pueden tener problemas
     * distintos, y eso cambia qué se hace al respecto.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function perfil(Collection $filas, string $dimension): array
    {
        $minimo = in_array($dimension, self::PERSONAS, true) ? self::PIEZAS_MINIMAS_PERSONA : self::PIEZAS_MINIMAS;
        $salida = [];

        foreach ($filas->groupBy('fase')->sortKeys() as $fase => $deFase) {
            $mezcla = $this->contarDefectos($deFase);
            $totalFase = array_sum($mezcla);
            $grupos = $deFase
                ->filter(fn (array $fila): bool => filled($fila[$dimension]))
                ->groupBy(fn (array $fila): string => (string) $fila[$dimension]);

            foreach ($grupos as $grupo) {
                $piezas = $grupo->pluck('pieza')->unique()->count();

                if ($piezas < 2) {
                    continue;
                }

                $propios = $this->contarDefectos($grupo);
                $total = array_sum($propios);
                $suficiente = $piezas >= $minimo;
                $caracteristico = null;

                foreach ($propios as $defecto => $veces) {
                    if (! $suficiente || $totalFase === 0 || $veces < self::CASOS_CARACTERISTICO || preg_match(self::GENERICOS, (string) $defecto)) {
                        continue;
                    }

                    $enLaEtapa = ($mezcla[$defecto] ?? 0) / $totalFase;
                    $indice = ($veces / $total) / $enLaEtapa;

                    if ($caracteristico === null || $indice > $caracteristico['indice']) {
                        $caracteristico = ['defecto' => (string) $defecto, 'indice' => $indice];
                    }
                }

                $juzgadas = $this->porPieza($grupo)->where('conVeredicto', true);

                $salida[] = [
                    'fase' => (string) $fase,
                    'nombre' => (string) $grupo->first()[$dimension],
                    'piezas' => $piezas,
                    'inspecciones' => $grupo->count(),
                    'defectos' => $total,
                    'defPorPieza' => round($total / $piezas, 2),
                    'rechazo' => $suficiente && $juzgadas->isNotEmpty() ? $this->tasaDeRechazo($juzgadas) : null,
                    'minimo' => $minimo,
                    'top' => collect(array_slice($propios, 0, 3, true))
                        ->map(fn (int $veces, int|string $defecto): array => [
                            'defecto' => (string) $defecto,
                            'n' => $veces,
                            'share' => round($veces * 100 / $total, 1),
                        ])
                        ->values()
                        ->all(),
                    'caracteristico' => $caracteristico === null
                        ? null
                        : ['defecto' => $caracteristico['defecto'], 'indice' => round($caracteristico['indice'], 1)],
                ];
            }
        }

        usort($salida, fn (array $a, array $b): int => [$a['fase'], -$a['defectos']] <=> [$b['fase'], -$b['defectos']]);

        return $salida;
    }

    /**
     * Los defectos de una fila: los del catálogo (soldadura, pintura) y las
     * fallas de puntos (armado, 1ª), que es como esas etapas los capturan.
     *
     * @param  array<string, mixed>  $fila
     * @return array<string, int>
     */
    private function defectosDe(array $fila): array
    {
        $todos = $fila['defectos'];

        foreach ($fila['fallas'] as $falla => $veces) {
            $todos[$falla] = ($todos[$falla] ?? 0) + $veces;
        }

        return $todos;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array<string, int> de más a menos frecuente
     */
    private function contarDefectos(Collection $filas): array
    {
        $cuenta = [];

        foreach ($filas as $fila) {
            foreach ($this->defectosDe($fila) as $defecto => $veces) {
                $cuenta[$defecto] = ($cuenta[$defecto] ?? 0) + $veces;
            }
        }

        arsort($cuenta);

        return $cuenta;
    }
}
