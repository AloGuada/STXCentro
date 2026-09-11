<?php

namespace App\Services\Qal\Formatos;

use App\Enums\Qal\FaseTransformacion;

/**
 * FC-STX-CA-07 · Medición de espesores de pintura (SSPC-PA2). Va al dosier.
 *
 * Cada columna es una medición —el promedio de sus lecturas del calibre— y el
 * espesor de la pieza es el promedio de las mediciones. Salen al menos cinco
 * columnas, y tantas como la pieza que más midió. Una medición por debajo del
 * 80 % del requerido se pinta en amarillo: es advertencia, no rechazo.
 *
 * Sólo entran las piezas que tienen espesores: la que se inspeccionó sin
 * medir no tiene nada que decir en esta hoja (la visual de pintura la avisa).
 */
class Espesores extends Formato
{
    private const MINIMO_DE_COLUMNAS = 5;

    public function clave(): string
    {
        return 'espesores';
    }

    public function codigo(): string
    {
        return 'FC-STX-CA-07';
    }

    public function revision(): string
    {
        return 'Rev. 0';
    }

    public function titulo(): string
    {
        return 'Medición de espesores de pintura';
    }

    public function subtitulo(): string
    {
        return 'Steelex Estructuras Metálicas · SSPC-PA2';
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
        return 'espesores';
    }

    public function datos(FiltrosDeReporte $filtros): array
    {
        ['filas' => $filas, 'universo' => $universo, 'total' => $total] = $this->filas->de($this->fase(), null, $filtros);
        $filas = $this->filas->ordenadas($this->vistas->aplicar($filas, $universo, $filtros->vista))
            ->filter(fn (array $fila): bool => $fila['pintura'] !== null && ($fila['pintura']['promedio'] !== null || $fila['pintura']['mediciones'] !== []))
            ->values();

        $mayor = $filas->map(fn (array $fila): int => $fila['pintura']['mediciones'] === [] ? 0 : max(array_keys($fila['pintura']['mediciones'])))->max();
        $columnas = max(self::MINIMO_DE_COLUMNAS, (int) $mayor);

        $renglones = $filas->map(function (array $fila, int $i) use ($columnas): array {
            $pintura = $fila['pintura'];
            $requerido = $pintura['requerido'];

            return [
                'no' => $i + 1,
                'pieza' => $this->pieza($fila),
                'mediciones' => array_map(function (int $medicion) use ($pintura, $requerido): array {
                    $valor = $pintura['mediciones'][$medicion] ?? null;

                    return [
                        'texto' => $this->mils($valor),
                        'bajo' => $valor !== null && $requerido !== null && $requerido > 0 && $valor < 0.8 * $requerido,
                    ];
                }, range(1, $columnas)),
                'promedio' => $this->mils($pintura['promedio']),
                'requerido' => $this->mils($requerido),
                'inspeccion' => $fila['inspeccion'],
                'resultado' => match ($pintura['cumple']) {
                    true => ['texto' => 'Aceptado', 'clase' => 'a-ok'],
                    false => ['texto' => 'Rechazado', 'clase' => 'a-def'],
                    default => ['texto' => '', 'clase' => ''],
                },
            ];
        });

        return [
            'generales' => [
                'Proyecto / Obra' => $this->obra($filtros->obraId),
                'Método' => 'SSPC-PA2 (calibre magnético)',
                'Unidad' => 'mils',
                'Periodo' => $filtros->periodoTexto(),
            ],
            'columnas' => $columnas,
            'renglones' => $renglones->all(),
            'vacio' => $renglones->isEmpty() ? $this->vacio($total, 'inspecciones de pintura', $filtros) : null,
            'nota' => $this->vistas->nota($filas, $filtros->vista),
            'leyenda' => 'SSPC-PA2: cada medición (columna) es el promedio de sus lecturas. El espesor de la pieza es el promedio '
                .'de las mediciones. Aceptación: promedio ≥ requerido. Celda amarilla = medición por debajo del 80 % del mínimo '
                .'(advertencia, no rechazo).',
            'creador_id' => $this->creador($filas),
        ];
    }
}
