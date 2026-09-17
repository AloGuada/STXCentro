<?php

namespace App\Services\Qal\Formatos;

use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use Carbon\CarbonImmutable;

/**
 * F-STX-CA-09 · Registro de inspección de armado y vestido. Control de taller:
 * no va al dosier —al cliente se le entrega la pieza terminada, no cómo se
 * llegó a ella—.
 *
 * Aquí no existe «liberada»: la pieza que sale bien queda pendiente porque
 * pasa a soldado, así que la hoja la escribe «Aceptado».
 */
class ArmadoVestido extends Formato
{
    public const PREPARACION = [
        'p2_bisel' => 'Bisel',
        'p2_pulido' => 'Pulido',
        'p2_raiz' => 'Raíz',
        'p2_respaldo' => 'Respaldo',
        'p2_hombro' => 'Hombro',
        'p2_acceso' => 'Acceso',
    ];

    public const ARMADO = [
        'p2_long' => 'Long.',
        'p2_placas' => 'Dist. placas',
        'p2_diaf' => 'Giro / caída',
        'p2_corte' => 'Corte',
    ];

    public function clave(): string
    {
        return 'armado';
    }

    public function codigo(): string
    {
        return 'F-STX-CA-09';
    }

    public function revision(): string
    {
        return 'Rev. 0';
    }

    public function titulo(): string
    {
        return 'Registro de inspección de armado y vestido';
    }

    public function subtitulo(): string
    {
        return 'Steelex Estructuras Metálicas · 2ª Transformación · control interno · AWS D1.1';
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
        return Subetapa::ArmadoVestido;
    }

    public function vista(): string
    {
        return 'armado';
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
            'inspector' => $fila['inspector'],
            'preparacion' => array_map(fn (string $clave): array => Celda::punto($fila['puntos'][$clave] ?? null), array_keys(self::PREPARACION)),
            'armado' => array_map(fn (string $clave): array => Celda::punto($fila['puntos'][$clave] ?? null), array_keys(self::ARMADO)),
            'falta_vestido' => (int) ($fila['puntos']['p2_faltavest']['numero'] ?? 0),
            'inspeccion' => $fila['inspeccion'],
            'resultado' => Celda::estatus($fila['estatus'], armado: true),
            'observaciones' => $this->observaciones($fila, $filtros),
        ]);

        return [
            'generales' => [
                'Proyecto / Obra' => $this->obra($filtros->obraId),
                'Etapa' => 'Armado / vestido',
                'Norma' => 'AWS D1.1',
                'Periodo' => $filtros->periodoTexto(),
            ],
            'renglones' => $renglones->all(),
            'totales' => [
                'kg' => $renglones->sum('kg'),
                'piezas' => $renglones->count(),
                'falta_vestido' => $renglones->sum('falta_vestido'),
                'aceptadas' => $filas->whereIn('estatus', ['pendiente', 'liberado'])->count(),
                'rechazadas' => $filas->where('estatus', 'rechazado')->count(),
            ],
            'vacio' => $renglones->isEmpty() ? $this->vacio($total, 'inspecciones de armado y vestido', $filtros) : null,
            'nota' => $this->vistas->nota($filas, $filtros->vista),
            'leyenda' => 'A = aceptado · D = con defecto · N/A = no aplica · — = no evaluado. Falta de vestido: elementos '
                .'faltantes respecto al plano; cualquier faltante rechaza la pieza. Aceptado = pasa a soldado.',
            'creador_id' => $this->creador($filas),
        ];
    }
}
