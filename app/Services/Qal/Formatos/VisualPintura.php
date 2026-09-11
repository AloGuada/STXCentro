<?php

namespace App\Services\Qal\Formatos;

use App\Enums\Qal\FaseTransformacion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Inspección visual de pintura. Va al dosier, pero todavía no tiene código de
 * formato asignado: sale como «Formato por asignar · Borrador», igual que en
 * la aplicación anterior.
 *
 * Una fila por pieza con los tres criterios, los defectos marcados y el
 * espesor promedio. La adherencia toma el resultado de la prueba ASTM D3359
 * cuando se hizo; si no, la valoración visual del inspector.
 */
class VisualPintura extends Formato
{
    /** Columna del formato ⇒ cómo se llama el defecto en el catálogo de pintura. */
    public const DEFECTOS = [
        'FP' => ['Falta pintura (FP)', 'Falta pintura'],
        'EB' => ['Espesor bajo (EB)', 'Espesor bajo'],
        'FA' => ['Falta adherencia (FA)', 'Falta adherencia'],
        'LIM' => ['Falta limpieza (LIM)', 'Falta limpieza'],
        'Otro' => ['Otro'],
    ];

    public function clave(): string
    {
        return 'visual-pintura';
    }

    public function codigo(): string
    {
        return 'Formato por asignar';
    }

    public function revision(): string
    {
        return 'Borrador';
    }

    public function titulo(): string
    {
        return 'Inspección visual de pintura';
    }

    public function subtitulo(): string
    {
        return 'Steelex Estructuras Metálicas · 3ª Transformación · SSPC-PA2 / ASTM D3359';
    }

    public function destino(): string
    {
        return self::DOSIER;
    }

    public function fase(): FaseTransformacion
    {
        return FaseTransformacion::Tercera;
    }

    public function vista(): string
    {
        return 'visual-pintura';
    }

    public function datos(FiltrosDeReporte $filtros): array
    {
        ['filas' => $filas, 'universo' => $universo, 'total' => $total] = $this->filas->de($this->fase(), null, $filtros);
        $filas = $this->filas->ordenadas($this->vistas->aplicar($filas, $universo, $filtros->vista));

        $renglones = $filas->map(function (array $fila, int $i) use ($filtros): array {
            $limpia = $this->limpia($fila, $filtros);
            $columnas = $this->columnasDeDefectos($fila['defectos']);

            return [
                'no' => $i + 1,
                'fecha' => CarbonImmutable::parse($fila['fecha'])->format('d/m'),
                'marca' => $fila['marca'],
                'consecutivo' => $fila['consecutivo'],
                'modulo' => $fila['modulo'],
                'area' => $fila['pintura']['area'] ?? null,
                'inspector' => $fila['inspector'],
                'criterios' => [
                    Celda::punto($fila['puntos']['p3_esp'] ?? null, $limpia),
                    Celda::punto($fila['puntos']['p3_vis'] ?? null, $limpia),
                    Celda::punto($this->adherencia($fila), $limpia),
                ],
                'defectos' => array_map(
                    fn (string $columna): array => ! $limpia && in_array($columna, $columnas, true)
                        ? ['texto' => '✗', 'clase' => 'a-def sim']
                        : ['texto' => '', 'clase' => ''],
                    array_keys(self::DEFECTOS),
                ),
                'promedio' => $fila['pintura']['promedio'] ?? null,
                'requerido' => $this->mils($fila['pintura']['requerido'] ?? null),
                'inspeccion' => $fila['inspeccion'],
                'resultado' => Celda::estatus($fila['estatus']),
                'observaciones' => $this->observaciones($fila, $filtros),
            ];
        });

        $sinEspesor = $renglones->whereNull('promedio');

        return [
            'generales' => [
                'Obra' => $this->obra($filtros->obraId),
                'Sistema' => $filas->pluck('pintura.metodo')->filter()->unique()->implode(', ') ?: '—',
                'Norma' => 'SSPC-PA2 · ASTM D3359',
                'Periodo' => $filtros->periodoTexto(),
            ],
            'defectos' => array_keys(self::DEFECTOS),
            'renglones' => $renglones->map(fn (array $r): array => [...$r, 'promedio' => $r['promedio'] === null ? null : $this->mils($r['promedio'])])->all(),
            'totales' => [
                'area' => $renglones->sum('area'),
                'piezas' => $renglones->count(),
                'liberadas' => $filas->where('estatus', 'liberado')->count(),
                'rechazadas' => $filas->where('estatus', 'rechazado')->count(),
                'pendientes' => $filas->where('estatus', 'pendiente')->count(),
            ],
            // Es un dato de captura, no un fallo del reporte: conviene que se vea para poder reclamarlo.
            'aviso' => $sinEspesor->isEmpty() ? null : $sinEspesor->count().' de '.$renglones->count()
                .' pieza(s) no tienen medición de espesor registrada (celda «s/m»): '
                .$sinEspesor->take(12)->pluck('marca')->implode(' · ').($sinEspesor->count() > 12 ? ' … y '.($sinEspesor->count() - 12).' más' : '')
                .'. Hay que capturar las lecturas del calibre en el registro de esa pieza para que salgan en el dosier.',
            'vacio' => $renglones->isEmpty() ? $this->vacio($total, 'inspecciones de pintura', $filtros) : null,
            'nota' => $this->vistas->nota($filas, $filtros->vista),
            'leyenda' => 'A = aceptado · D = con defecto · N/A = no aplica. FP falta pintura · EB espesor bajo · FA falta adherencia · '
                .'LIM falta limpieza. La columna Adher. toma el resultado de la prueba ASTM D3359 cuando existe; si no, la valoración '
                .'visual. Insp. mayor que 1 indica re-inspección tras retrabajo. El detalle de las mediciones está en el FC-STX-CA-07.',
            'creador_id' => $this->creador($filas),
        ];
    }

    /**
     * La prueba de adherencia manda sobre la valoración visual.
     *
     * @param  array<string, mixed>  $fila
     * @return array{resultado: string|null, valor: string|null}|null
     */
    private function adherencia(array $fila): ?array
    {
        return match ($fila['adherencia']['resultado'] ?? null) {
            'Aceptado' => ['resultado' => 'ok', 'valor' => null],
            'Rechazado' => ['resultado' => 'no_ok', 'valor' => null],
            default => $fila['puntos']['p3_adh'] ?? null,
        };
    }

    /**
     * Las columnas que marcan los defectos de la pieza. Uno que no tiene
     * columna propia cae en «Otro»: en esta hoja no se puede perder.
     *
     * @param  array<string, int>  $defectos
     * @return list<string>
     */
    private function columnasDeDefectos(array $defectos): array
    {
        $columnaDe = collect(self::DEFECTOS)
            ->flatMap(fn (array $nombres, string $columna): Collection => collect($nombres)->mapWithKeys(fn (string $nombre): array => [mb_strtolower($nombre) => $columna]));

        return collect($defectos)
            ->filter(fn (int $cantidad): bool => $cantidad > 0)
            ->keys()
            ->map(fn (string $nombre): string => $columnaDe[mb_strtolower($nombre)] ?? 'Otro')
            ->unique()
            ->values()
            ->all();
    }
}
