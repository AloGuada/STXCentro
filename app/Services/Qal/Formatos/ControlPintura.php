<?php

namespace App\Services\Qal\Formatos;

use App\Enums\Qal\FaseTransformacion;

/**
 * F-STX-CA-06 · Control diario de inspección de pintura. De uso interno.
 *
 * Una fila por pieza, como quedó, con las tres pruebas en ✓/✗ y lo que pasó en
 * cada revisión: R1, R2 y R3 son el resultado de su primera, segunda y
 * tercera inspección, estén o no dentro del periodo. Por eso no ofrece elegir
 * la vista: siempre es la pieza con su historia.
 *
 * El total es de kilos LIBERADOS, como dice el renglón: la aplicación anterior
 * sumaba todas las piezas de la hoja bajo ese rótulo.
 */
class ControlPintura extends Formato
{
    public function clave(): string
    {
        return 'control-pintura';
    }

    public function codigo(): string
    {
        return 'F-STX-CA-06';
    }

    public function revision(): string
    {
        return 'Revisión 2';
    }

    public function titulo(): string
    {
        return 'Control diario de inspección de pintura';
    }

    public function subtitulo(): string
    {
        return 'Steelex Estructuras Metálicas';
    }

    public function destino(): string
    {
        return self::INTERNO;
    }

    public function fase(): FaseTransformacion
    {
        return FaseTransformacion::Tercera;
    }

    public function usaVista(): bool
    {
        return false;
    }

    public function vistaPorDefecto(): string
    {
        return 'final';
    }

    public function vista(): string
    {
        return 'control-pintura';
    }

    public function datos(FiltrosDeReporte $filtros): array
    {
        $filtros = $filtros->conVista('final');
        ['filas' => $filas, 'universo' => $universo, 'total' => $total] = $this->filas->de($this->fase(), null, $filtros);
        $filas = $this->filas->ordenadas($this->vistas->aplicar($filas, $universo, 'final'));
        $historias = $universo->groupBy('pieza');

        $renglones = $filas->map(function (array $fila) use ($historias): array {
            $historia = $historias[$fila['pieza']] ?? collect([$fila]);
            $adherencia = $fila['adherencia']['resultado'] ?? null;
            $rechazo = array_keys(array_filter($fila['defectos']));

            if (($fila['puntos']['p3_esp']['resultado'] ?? null) === 'no_ok' && ! collect($rechazo)->contains(fn (string $d): bool => str_contains($d, 'EB'))) {
                $rechazo[] = 'EB';
            }

            return [
                'marca' => $fila['marca'],
                'consecutivo' => $fila['consecutivo'],
                'modulo' => $fila['modulo'],
                'pruebas' => [
                    $this->prueba($fila['puntos']['p3_esp']['resultado'] ?? null),
                    $this->prueba($fila['puntos']['p3_vis']['resultado'] ?? null),
                    $this->prueba(match ($adherencia) {
                        'Aceptado' => 'ok',
                        'Rechazado' => 'no_ok',
                        default => $fila['puntos']['p3_adh']['resultado'] ?? null,
                    }),
                ],
                'rechazo' => implode('; ', $rechazo),
                'revisiones' => array_map(function (int $n) use ($historia): array {
                    $intento = $historia->firstWhere('inspeccion', $n);

                    return $this->prueba(match ($intento['estatus'] ?? null) {
                        'liberado' => 'ok',
                        'rechazado' => 'no_ok',
                        default => null,
                    });
                }, [1, 2, 3]),
                'kg' => $fila['kg'],
                'resultado' => Celda::estatus($fila['estatus']),
                'observaciones' => $fila['observaciones'],
            ];
        });

        return [
            'generales' => [
                'Área' => 'Pintura',
                'Obra' => $this->obra($filtros->obraId),
                'Periodo' => $filtros->periodoTexto(),
            ],
            'renglones' => $renglones->all(),
            'kg_liberados' => $filas->where('estatus', 'liberado')->sum('kg'),
            'vacio' => $renglones->isEmpty() ? $this->vacio($total, 'inspecciones de pintura', $filtros) : null,
            'nota' => null,
            'leyenda' => '✓ = pasa · ✗ = rechazo · EB = espesor bajo · FP = falta pintura · LIM = limpieza · FA = falta adherencia. '
                .'R1 / R2 / R3 = resultado en cada revisión de la pieza. El total suma sólo las piezas liberadas.',
            'creador_id' => $this->creador($filas),
        ];
    }

    /**
     * @return array{texto: string, clase: string}
     */
    private function prueba(?string $resultado): array
    {
        return match ($resultado) {
            'ok' => ['texto' => '✓', 'clase' => 'a-ok sim'],
            'no_ok' => ['texto' => '✗', 'clase' => 'a-def sim'],
            default => ['texto' => '', 'clase' => ''],
        };
    }
}
