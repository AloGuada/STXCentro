<?php

namespace App\Services\Costos;

use Illuminate\Support\Collection;

/**
 * Tamaño de hoja y anchos de columna del reporte de presupuestos en PDF.
 *
 * El reporte lleva una columna por rubro, así que su ancho crece con el
 * catálogo. En lugar de una hoja fija, se mide lo que ocupa cada columna con
 * los montos reales y se escoge la hoja estándar más chica en la que cabe
 * todo (tabloide, A2, A1, A0, 2A0); si ni la 2A0 alcanza, la hoja se hace a la
 * medida. Las cifras van en Courier, que es monoespaciada: su ancho por
 * carácter es exacto.
 *
 * Unidades: dompdf dibuja a 96 dpi, así que 1px de CSS son 0.75pt de PDF.
 */
class HojaReportePresupuestos
{
    /** Margen de la página en pt (se inyecta en el `@page` de la vista). */
    public const MARGEN_PAGINA_PT = 28.0;

    /** Padding horizontal del body en px, sumando ambos lados. */
    private const PADDING_BODY_PX = 24;

    private const PT_POR_PX = 0.75;

    /** Tamaño de letra de las celdas en px. */
    private const LETRA_CELDA_PX = 6.5;

    /** Courier ocupa 0.6em por carácter. */
    private const ANCHO_COURIER_EM = 0.6;

    /** Estimación holgada de Arial en negritas y mayúsculas. */
    private const ANCHO_ARIAL_NEGRITA_EM = 0.75;

    /**
     * Lo que dompdf suma por fuera del `width` de cada celda: 6px de padding y
     * 1px de borde (medido dibujando), más 1px de margen.
     */
    private const EXTRA_POR_COLUMNA_PX = 8;

    /** Holgura sobre el total estimado. */
    private const HOLGURA = 1.03;

    /**
     * Hojas candidatas en pt, horizontales: [ancho, alto].
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private const HOJAS = [
        'Tabloide' => [1224.00, 792.00],
        'A2' => [1683.78, 1190.55],
        'A1' => [2383.94, 1683.78],
        'A0' => [3370.39, 2383.94],
        '2A0' => [4767.87, 3370.39],
    ];

    /**
     * @param  array{proyecto: int, nombre: int, etiqueta: int, dinero: int, porcentaje: int, rubros: array<int, int>}  $columnas  `width` de CSS de cada columna en px, sin padding ni borde
     * @param  array{0: float, 1: float, 2: float, 3: float}  $papel  caja del papel en pt, ya horizontal
     */
    public function __construct(
        public readonly array $columnas,
        public readonly array $papel,
        public readonly string $nombreHoja,
    ) {}

    /**
     * @param  Collection<int, \App\Models\Costos\TipoRubro>  $tipos  con sus `rubros` cargados
     * @param  Collection<int, array<string, mixed>>  $filas  las filas que arma el controlador
     */
    public static function para(Collection $tipos, Collection $filas): self
    {
        $dinero = max(50, self::anchoCifra(self::cifraMasLarga($filas, self::montos(...), fn (float $v) => '$'.number_format($v, 2))));
        $porcentaje = max(26, self::anchoCifra(self::cifraMasLarga($filas, self::porcentajes(...), fn (float $v) => number_format($v, 2).'%')));

        $proyecto = max(48, self::anchoTexto($filas->map(fn (array $fila) => (string) $fila['obra']->no)->all()));

        $rubros = [];
        foreach ($tipos as $tipo) {
            foreach ($tipo->rubros as $rubro) {
                $rubros[$rubro->id] = max($dinero, self::anchoTexto([strtoupper((string) $rubro->codigo)]));
            }
        }

        $columnas = [
            'proyecto' => $proyecto,
            'nombre' => 120,
            'etiqueta' => 30,
            'dinero' => $dinero,
            'porcentaje' => $porcentaje,
            'rubros' => $rubros,
        ];

        $totalColumnas = 4 + 2 * $tipos->count() + count($rubros) + 6;

        $anchoTablaPx = $proyecto + 120 + 30 + $dinero
            + $tipos->count() * ($dinero + $porcentaje)
            + array_sum($rubros)
            + 2 * $dinero + 4 * $porcentaje
            + $totalColumnas * self::EXTRA_POR_COLUMNA_PX;

        $anchoNecesarioPt = ($anchoTablaPx + self::PADDING_BODY_PX) * self::PT_POR_PX * self::HOLGURA
            + 2 * self::MARGEN_PAGINA_PT;

        foreach (self::HOJAS as $nombre => [$ancho, $alto]) {
            if ($anchoNecesarioPt <= $ancho) {
                return new self($columnas, [0.0, 0.0, $ancho, $alto], $nombre);
            }
        }

        return new self($columnas, [0.0, 0.0, ceil($anchoNecesarioPt), ceil($anchoNecesarioPt / M_SQRT2)], 'A la medida');
    }

    /**
     * Todos los montos que pinta una fila.
     *
     * @param  array<string, mixed>  $fila
     * @return list<float>
     */
    private static function montos(array $fila): array
    {
        $montos = [
            $fila['ingresos_presup'], $fila['ingresos_real'], $fila['ingresos_dif'],
            $fila['total_presup'], $fila['total_real'], $fila['total_dif'], $fila['utilidad'],
        ];

        foreach ($fila['tipos'] as $tipo) {
            array_push($montos, $tipo['total_presup'], $tipo['total_real'], $tipo['total_dif']);

            foreach ($tipo['rubros'] as $rubro) {
                array_push($montos, $rubro['presup'], $rubro['real'], $rubro['dif']);
            }
        }

        return array_map(floatval(...), $montos);
    }

    /**
     * Todos los porcentajes que pinta una fila.
     *
     * @param  array<string, mixed>  $fila
     * @return list<float>
     */
    private static function porcentajes(array $fila): array
    {
        $porcentajes = [$fila['total_pct'], $fila['util_vts_pct'], $fila['utilidad_pct']];

        foreach ($fila['tipos'] as $tipo) {
            $porcentajes[] = $tipo['porcentaje'];
        }

        return array_map(floatval(...), $porcentajes);
    }

    /**
     * Largo en caracteres de la cifra formateada más larga.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @param  callable(array<string, mixed>): list<float>  $valores
     * @param  callable(float): string  $formato
     */
    private static function cifraMasLarga(Collection $filas, callable $valores, callable $formato): int
    {
        $largo = 0;

        foreach ($filas as $fila) {
            foreach ($valores($fila) as $valor) {
                $largo = max($largo, strlen($formato($valor)));
            }
        }

        return $largo;
    }

    private static function anchoCifra(int $caracteres): int
    {
        return (int) ceil($caracteres * self::LETRA_CELDA_PX * self::ANCHO_COURIER_EM);
    }

    /**
     * Ancho de la palabra más larga: el texto se parte en espacios, pero una
     * palabra sola no se puede partir y empuja la columna.
     *
     * @param  list<string>  $textos
     */
    private static function anchoTexto(array $textos): int
    {
        $largo = 0;

        foreach ($textos as $texto) {
            foreach (preg_split('/\s+/', $texto) ?: [] as $palabra) {
                $largo = max($largo, mb_strlen($palabra));
            }
        }

        return (int) ceil($largo * 7 * self::ANCHO_ARIAL_NEGRITA_EM);
    }
}
