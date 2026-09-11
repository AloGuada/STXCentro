<?php

namespace App\Services\Qal\Formatos;

use App\Enums\Qal\FaseTransformacion;
use Carbon\CarbonImmutable;

/**
 * Inspección de 1ª transformación: corte, habilitado y barrenado. De uso
 * interno y todavía sin código de formato asignado.
 *
 * Incluye el muestreo del lote, que en 1ª es la forma normal de trabajar: se
 * inspecciona una muestra y el veredicto aplica al lote completo. «Rech.» son
 * las piezas rechazadas dentro de la muestra, no del lote.
 */
class PrimeraTransformacion extends Formato
{
    public const CRITERIOS = [
        'p1_dim' => 'Dimensión',
        'p1_long' => 'Longitud',
        'p1_defl' => 'Deflexión',
        'p1_tors' => 'Torsión',
        'p1_patin' => 'Descuadre de patín',
        'p1_corte' => 'Defectos de corte',
        'p1_bisel' => 'Bisel',
        'p1_posbar' => 'Posición de barrenos',
        'p1_diam' => 'Diámetro de barrenos',
        'p1_bar' => 'Barrenos',
        'p1_limpieza' => 'Limpieza',
        'p1_empates' => 'Empates / juntas',
    ];

    public function clave(): string
    {
        return 'primera';
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
        return 'Inspección de 1ª transformación';
    }

    public function subtitulo(): string
    {
        return 'Steelex Estructuras Metálicas · corte, habilitado y barrenado · control interno';
    }

    public function destino(): string
    {
        return self::INTERNO;
    }

    public function fase(): FaseTransformacion
    {
        return FaseTransformacion::Primera;
    }

    public function vista(): string
    {
        return 'primera';
    }

    public function datos(FiltrosDeReporte $filtros): array
    {
        ['filas' => $filas, 'universo' => $universo, 'total' => $total] = $this->filas->de($this->fase(), null, $filtros);
        $filas = $this->filas->ordenadas($this->vistas->aplicar($filas, $universo, $filtros->vista));

        $renglones = $filas->map(fn (array $fila, int $i): array => [
            'no' => $i + 1,
            'fecha' => CarbonImmutable::parse($fila['fecha'])->format('d/m'),
            'marca' => $this->pieza($fila),
            'tipo' => trim(($fila['subtipo'] ?? '').' '.($fila['tipo'] ?? '')),
            'cantidad' => $fila['cantidad_lote'],
            'equipo' => $fila['equipo'],
            'operador' => $fila['operador'],
            'inspector' => $fila['inspector'],
            'criterios' => array_map(fn (string $clave): array => Celda::punto($fila['puntos'][$clave] ?? null), array_keys(self::CRITERIOS)),
            'muestreo' => $fila['muestreo'] ? [
                'lote' => $fila['muestreo']['lote'],
                'muestra' => $fila['muestreo']['muestra'],
                'rechazadas' => (int) $fila['muestreo']['rechazadas'],
                'veredicto' => match ($fila['muestreo']['veredicto']) {
                    'aceptado' => ['texto' => 'Aceptado', 'clase' => 'a-ok'],
                    'rechazado' => ['texto' => 'Rechazado', 'clase' => 'a-def'],
                    default => ['texto' => 'En curso', 'clase' => 'a-pen'],
                },
            ] : null,
            'inspeccion' => $fila['inspeccion'],
            'resultado' => Celda::estatus($fila['estatus']),
            'observaciones' => $this->observaciones($fila, $filtros),
        ]);

        return [
            'generales' => [
                'Obra' => $this->obra($filtros->obraId),
                'Línea' => $filas->pluck('linea')->filter()->unique()->sort()->implode(', '),
                'Norma' => 'AISC / plano de fabricación',
                'Periodo' => $filtros->periodoTexto(),
            ],
            'criterios' => array_values(self::CRITERIOS),
            'renglones' => $renglones->all(),
            'totales' => [
                'piezas' => (int) $renglones->sum('cantidad'),
                'registros' => $renglones->count(),
                'liberadas' => $filas->where('estatus', 'liberado')->count(),
                'rechazadas' => $filas->where('estatus', 'rechazado')->count(),
                'pendientes' => $filas->where('estatus', 'pendiente')->count(),
            ],
            'vacio' => $renglones->isEmpty() ? $this->vacio($total, 'inspecciones de 1ª transformación', $filtros) : null,
            'nota' => $this->vistas->nota($filas, $filtros->vista),
            'leyenda' => 'A = aceptado · D = con defecto · N/A = no aplica · — = no evaluado. El muestreo sigue ANSI/ASQ Z1.4: se '
                .'inspecciona una muestra del lote y el veredicto aplica al lote completo. Rech. es el número de piezas rechazadas '
                .'dentro de la muestra, no del lote.',
            'creador_id' => $this->creador($filas),
        ];
    }
}
