<?php

namespace App\Services\Qal\Formatos;

use App\Enums\Qal\FaseTransformacion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * FC-STX-CA-04 · Inspección visual de soldadura, el formato del dosier. Una
 * fila por MARCA con 18 criterios agrupados en «antes de soldar», «durante» y
 * «después de soldar».
 *
 * La convención es la del papel: A aceptado para antes y durante; DN «dentro
 * de norma» para los defectos de después —no se marcan bien, se declara que
 * están en norma—; N/A no aplica; — sin registro de esa etapa.
 *
 * Antes sale de las inspecciones de armado y vestido de las piezas de la
 * marca, durante y después de las de soldado. Con varias piezas manda la peor:
 * basta una con defecto para que la marca lo diga, salvo que todas quedaran
 * liberadas y la hoja sea la del dosier. Si la marca no tiene armado, esas
 * columnas salen «—» en vez de inventar un aceptado.
 */
class VisualSoldadura extends Formato
{
    /** Criterios de antes de soldar: clave del punto de armado ⇒ rótulo del formato. */
    public const ANTES = [
        'p2_bisel' => 'Preparación de junta para filete',
        'p2_raiz' => 'Preparación de junta para ranura',
        'p2_respaldo' => 'Placa de respaldo',
        'p2_acceso' => 'Radios de acceso',
        'p2_corte' => 'Corte sin muescas',
    ];

    public const DURANTE = [
        'p2_precal' => 'Precalentamiento',
        'p2_limppasadas' => 'Limpieza entre pasadas',
    ];

    /** Defecto del formato ⇒ cómo se llama en el catálogo de defectos de soldadura. */
    public const DESPUES = [
        'Grieta' => ['Grieta'],
        'F/Fusión' => ['Falta de fusión'],
        'Traslape' => ['Traslape'],
        'Soldadura insuficiente' => ['Falta de soldadura', 'Soldadura convexa', 'Tamaño bajo lo nominal', 'Pierna baja', 'Garganta baja', 'Falta de relleno'],
        'Porosidad' => ['Porosidad / poros', 'Porosidad'],
        'Socavado' => ['Socavación', 'Socavado'],
        'Perfil de soldadura' => ['Perfil inaceptable', 'Soldadura cóncava', 'Piernas desiguales'],
        'Cráter' => ['Cráter sin llenar', 'Falta de remate'],
        'Retiro de puntos de soldadura' => ['Puntos de soldadura sin retirar'],
        'Material base dañado' => ['Daño de material', 'Golpe de arco'],
    ];

    public function clave(): string
    {
        return 'visual-soldadura';
    }

    public function codigo(): string
    {
        return 'FC-STX-CA-04';
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
        return 'TIM DEL MAYAB S.A. DE C.V.';
    }

    public function destino(): string
    {
        return self::DOSIER;
    }

    public function fase(): FaseTransformacion
    {
        return FaseTransformacion::Segunda;
    }

    public function vista(): string
    {
        return 'visual-soldadura';
    }

    public function datos(FiltrosDeReporte $filtros): array
    {
        ['filas' => $filas, 'universo' => $universo, 'total' => $total] = $this->filas->de($this->fase(), null, $filtros);
        $filas = $this->filas->ordenadas($this->vistas->aplicar($filas, $universo, $filtros->vista));

        $renglones = $filas
            ->groupBy('marca')
            ->sortKeysUsing(fn (string $a, string $b): int => strnatcasecmp($a, $b))
            ->map(fn (Collection $piezas, string $marca): array => $this->renglon($marca, $piezas, $filtros))
            ->values();

        $fechas = $filas->pluck('fecha')->sort()->values();

        return [
            'generales' => [
                'Obra' => $this->obra($filtros->obraId),
                'Línea de fabricación' => $filas->pluck('linea')->filter()->unique()->sort()->implode(', '),
                'Lugar de verificación' => 'Kanasín, Yucatán.',
                'Fecha de registro' => $fechas->isEmpty() ? '' : CarbonImmutable::parse($fechas->last())->format('d/m/Y'),
                'Periodo' => $filtros->periodoTexto(),
                'Norma aplicable' => 'AWS D1.1',
            ],
            'antes' => ['Material correcto', ...array_values(self::ANTES)],
            'durante' => array_values(self::DURANTE),
            'despues' => array_keys(self::DESPUES),
            'renglones' => $renglones->all(),
            'vacio' => $renglones->isEmpty() ? $this->vacio($total, 'inspecciones de 2ª transformación', $filtros) : null,
            'nota' => $this->vistas->nota($filas, $filtros->vista),
            'leyenda' => 'A = ACEPTADO · DN = DENTRO DE NORMA · N/A = NO APLICA · — = sin registro de esa etapa. Los criterios '
                .'de preparación provienen de la inspección de armado y vestido; el control de proceso y los defectos, de la '
                .'inspección de soldadura.',
            'creador_id' => $this->creador($filas),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $piezas
     * @return array<string, mixed>
     */
    private function renglon(string $marca, Collection $piezas, FiltrosDeReporte $filtros): array
    {
        $armados = $piezas->where('subetapa', 'armado_vestido');
        $soldados = $piezas->where('subetapa', 'soldado');
        $limpia = $soldados->isNotEmpty() && $soldados->every(fn (array $fila): bool => $this->limpia($fila, $filtros));

        [$conteo, $otros] = $limpia ? [[], []] : $this->defectos($soldados);

        $observaciones = $limpia ? [] : $piezas->pluck('observaciones')->filter()->unique()->values()->all();

        if ($otros !== []) {
            $observaciones[] = 'Otros defectos: '.collect($otros)->map(fn (int $n, string $nombre): string => "{$nombre}: {$n}")->implode('; ');
        }

        return [
            'marca' => $marca,
            'modulo' => $piezas->pluck('modulo')->filter()->unique()->sort()->implode(', '),
            'piezas' => $piezas->pluck('pieza')->unique()->count(),
            // Material correcto no se captura: se declara aceptado si hay inspección.
            'antes' => [
                Celda::aceptado(),
                ...array_map(fn (string $clave): array => $this->peor($armados, $clave, $limpia), array_keys(self::ANTES)),
            ],
            'durante' => array_map(fn (string $clave): array => $this->peor($soldados, $clave, $limpia), array_keys(self::DURANTE)),
            'despues' => array_map(
                fn (string $columna): array => $soldados->isEmpty()
                    ? Celda::sinRegistro()
                    : (($conteo[$columna] ?? 0) > 0 ? Celda::defecto() : ['texto' => 'DN', 'clase' => '']),
                array_keys(self::DESPUES),
            ),
            'observaciones' => $observaciones !== [] ? implode(' · ', $observaciones) : 'N/A',
        ];
    }

    /**
     * La peor respuesta del criterio entre las piezas: D si alguna tuvo defecto,
     * A si alguna salió bien, N/A si en todas no aplicaba.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array{texto: string, clase: string}
     */
    private function peor(Collection $filas, string $clave, bool $limpia): array
    {
        $resultados = $filas->map(fn (array $fila): ?string => $fila['puntos'][$clave]['resultado'] ?? null)->filter();

        return match (true) {
            $resultados->contains('no_ok') => $limpia ? Celda::aceptado() : Celda::defecto(),
            $resultados->contains('ok') => Celda::aceptado(),
            $resultados->contains('no_aplica') => ['texto' => 'N/A', 'clase' => ''],
            default => Celda::sinRegistro(),
        };
    }

    /**
     * Los defectos de soldado sumados por columna del formato, y los que no
     * tienen columna —van a Observaciones para no perderse—.
     *
     * @param  Collection<int, array<string, mixed>>  $soldados
     * @return array{0: array<string, int>, 1: array<string, int>}
     */
    private function defectos(Collection $soldados): array
    {
        $columnaDe = [];

        foreach (self::DESPUES as $columna => $nombres) {
            foreach ($nombres as $nombre) {
                $columnaDe[mb_strtolower($nombre)] = $columna;
            }
        }

        $conteo = [];
        $otros = [];

        foreach ($soldados as $fila) {
            foreach ($fila['defectos'] as $nombre => $cantidad) {
                $columna = $columnaDe[mb_strtolower($nombre)] ?? null;

                if ($columna) {
                    $conteo[$columna] = ($conteo[$columna] ?? 0) + $cantidad;
                } else {
                    $otros[$nombre] = ($otros[$nombre] ?? 0) + $cantidad;
                }
            }
        }

        return [$conteo, $otros];
    }
}
