<?php

namespace App\Services\Qal\Formatos;

use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use Carbon\CarbonImmutable;

/**
 * F-STX-CA-10 · Registro de inspección visual de soldadura. Una fila por
 * inspección de soldado, con el control de proceso, los defectos y el
 * acabado. Es de uso interno.
 *
 * El peso es el de ESA pieza: cada inspección es una pieza física, no el lote
 * de la marca. Los defectos por elemento permiten comparar piezas de distinto
 * tamaño.
 */
class Soldadura extends Formato
{
    public function clave(): string
    {
        return 'soldadura';
    }

    public function codigo(): string
    {
        return 'F-STX-CA-10';
    }

    public function revision(): string
    {
        return 'Rev. 0';
    }

    public function titulo(): string
    {
        return 'Registro de inspección visual de soldadura';
    }

    public function subtitulo(): string
    {
        return 'Steelex Estructuras Metálicas · 2ª Transformación · AWS D1.1';
    }

    public function destino(): string
    {
        return self::INTERNO;
    }

    public function fase(): FaseTransformacion
    {
        return FaseTransformacion::Segunda;
    }

    public function subetapa(): ?Subetapa
    {
        return Subetapa::Soldado;
    }

    public function vista(): string
    {
        return 'soldadura';
    }

    public function datos(FiltrosDeReporte $filtros): array
    {
        ['filas' => $filas, 'universo' => $universo, 'total' => $total] = $this->filas->de($this->fase(), $this->subetapa(), $filtros);
        $filas = $this->filas->ordenadas($this->vistas->aplicar($filas, $universo, $filtros->vista));

        $renglones = $filas->values()->map(fn (array $fila, int $i): array => [
            'no' => $i + 1,
            'fecha' => CarbonImmutable::parse($fila['fecha'])->format('d/m'),
            'marca' => $fila['marca'],
            'consecutivo' => $fila['consecutivo'],
            'kg' => $fila['kg'],
            'linea' => $fila['linea'],
            'modulo' => $fila['modulo'],
            'soldador' => $fila['soldador'],
            'inspector' => $fila['inspector'],
            'precalentamiento' => Celda::punto($fila['puntos']['p2_precal'] ?? null),
            'limpieza_pasadas' => Celda::punto($fila['puntos']['p2_limppasadas'] ?? null),
            'elementos' => $fila['puntos']['p2_elem']['numero'] ?? null,
            'defectos' => array_sum($fila['defectos']),
            'tipos' => collect($fila['defectos'])->map(fn (int $n, string $nombre): string => "{$nombre}: {$n}")->implode('; '),
            'limpieza' => Celda::punto($fila['puntos']['p2_limpieza'] ?? null),
            'etiqueta' => Celda::punto($fila['puntos']['p2_etiqueta'] ?? null),
            'inspeccion' => $fila['inspeccion'],
            'resultado' => Celda::estatus($fila['estatus']),
            'observaciones' => $this->observaciones($fila, $filtros),
        ]);

        $elementos = $renglones->sum('elementos');
        $defectos = $renglones->sum('defectos');

        return [
            'generales' => [
                'Proyecto / Obra' => $this->obra($filtros->obraId),
                'Etapa' => 'Soldado',
                'Norma' => 'AWS D1.1',
                'Periodo' => $filtros->periodoTexto(),
            ],
            'renglones' => $renglones->all(),
            'totales' => [
                'kg' => $renglones->sum('kg'),
                'piezas' => $renglones->count(),
                'elementos' => $elementos,
                'defectos' => $defectos,
                'por_elemento' => $elementos > 0 ? number_format($defectos / $elementos, 3) : '—',
                'liberadas' => $filas->where('estatus', 'liberado')->count(),
                'rechazadas' => $filas->where('estatus', 'rechazado')->count(),
                'pendientes' => $filas->where('estatus', 'pendiente')->count(),
            ],
            'vacio' => $renglones->isEmpty() ? $this->vacio($total, 'inspecciones de soldadura', $filtros) : null,
            'nota' => $this->vistas->nota($filas, $filtros->vista),
            'leyenda' => 'A = aceptado · D = con defecto · N/A = no aplica · — = no evaluado. Kg = peso de la pieza; el '
                .'total es el peso inspeccionado en el periodo. Elem. = elementos de la pieza según plano. Insp. = número '
                .'de inspección (mayor que 1 es re-inspección tras retrabajo). Criterio de aceptación conforme a AWS D1.1.',
            'creador_id' => $this->creador($filas),
        ];
    }
}
