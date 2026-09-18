<?php

namespace App\Services\Qal\Formatos;

use App\Enums\Qal\FaseTransformacion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * F-STX-CA-08 · Registro de prueba de adherencia (ASTM D3359). Va al dosier.
 *
 * Después de la tabla y de las firmas van las hojas de evidencia: lo que hoy
 * se hace pegando las tiras en un acetato y escaneándolo. Las fotos se
 * tomaron en la tablet al hacer la prueba y aquí se acomodan solas, seis por
 * hoja en 3 × 2, en el orden de la tabla. Si una pieza tiene varias se numeran
 * para que nadie adivine cuál es cuál. Un PDF adjunto no se puede incrustar en
 * el recuadro: se nombra.
 */
class PruebaDeAdherencia extends Formato
{
    public const POR_HOJA = 6;

    public const POR_RENGLON = 3;

    public function clave(): string
    {
        return 'adherencia';
    }

    public function codigo(): string
    {
        return 'F-STX-CA-08';
    }

    public function revision(): string
    {
        return 'Rev. 0';
    }

    public function titulo(): string
    {
        return 'Registro de prueba de adherencia';
    }

    public function subtitulo(): string
    {
        return 'Steelex Estructuras Metálicas · ASTM D3359';
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
        return 'adherencia';
    }

    public function datos(FiltrosDeReporte $filtros): array
    {
        ['filas' => $filas, 'universo' => $universo, 'total' => $total] = $this->filas->de($this->fase(), null, $filtros);
        $filas = $this->filas->ordenadas($this->vistas->aplicar($filas, $universo, $filtros->vista))
            ->filter(fn (array $fila): bool => $fila['adherencia'] !== null)
            ->values();

        $renglones = $filas->map(fn (array $fila, int $i): array => [
            'no' => $i + 1,
            'pieza' => $this->pieza($fila),
            // Cada tira lleva su método: en ASTM D3359 lo decide el espesor de
            // la película y las tres no siempre caen sobre el mismo.
            'tiras' => array_map(fn (int $orden): array => [
                'metodo' => (string) ($fila['adherencia']['tiras'][$orden]['metodo'] ?? ''),
                'clasificacion' => (string) ($fila['adherencia']['tiras'][$orden]['clasificacion'] ?? ''),
            ], [1, 2, 3]),
            'inspeccion' => $fila['inspeccion'],
            'resultado' => match ($fila['adherencia']['resultado']) {
                'Aceptado' => ['texto' => 'Aceptado', 'clase' => 'a-ok'],
                'Rechazado' => ['texto' => 'Rechazado', 'clase' => 'a-def'],
                default => ['texto' => '', 'clase' => ''],
            },
            'observaciones' => $this->observaciones($fila, $filtros),
        ]);

        return [
            'generales' => [
                'Proyecto / Obra' => $this->obra($filtros->obraId),
                'Método de prueba' => 'ASTM D3359',
                'Periodo' => $filtros->periodoTexto(),
            ],
            'renglones' => $renglones->all(),
            'evidencias' => $this->evidencias($filas->all()),
            'vacio' => $renglones->isEmpty() ? $this->vacio($total, 'inspecciones de pintura', $filtros) : null,
            'nota' => $this->vistas->nota($filas, $filtros->vista),
            'leyenda' => 'ASTM D3359: método A (en cruz, > 5 mils) o B (cuadrícula, < 5 mils). Clasificación 5 (mejor, sin '
                .'desprendimiento) a 0 (peor). Las fotos de la prueba van en las hojas de evidencia, al final.',
            'creador_id' => $this->creador($filas),
        ];
    }

    /**
     * Los recuadros de evidencia, de seis en seis. La hoja lleva la fecha de
     * la última prueba que contiene, como el acetato.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array{numero: int, fecha: string, marcos: list<array{rotulo: string, ruta: string|null, pdf: string|null}|null>}>
     */
    private function evidencias(array $filas): array
    {
        $disco = Storage::disk('public');
        $marcos = [];

        foreach ($filas as $fila) {
            $fotos = $fila['adherencia']['fotos'];

            foreach ($fotos as $i => $foto) {
                $marcos[] = [
                    'rotulo' => $this->pieza($fila).(count($fotos) > 1 ? ' ('.($i + 1).'/'.count($fotos).')' : ''),
                    'ruta' => $foto['imagen'] && $disco->exists($foto['path']) ? $disco->path($foto['path']) : null,
                    'pdf' => $foto['imagen'] ? null : ($foto['nombre'] ?? 'PDF adjunto'),
                    'fecha' => $fila['fecha'],
                ];
            }
        }

        $hojas = array_chunk($marcos, self::POR_HOJA);

        // Se completa sólo el renglón de tres: un renglón entero de recuadros
        // vacíos no dice nada y dompdf lo manda a una hoja más.
        return array_map(fn (array $lote, int $i): array => [
            'numero' => $i + 1,
            'fecha' => CarbonImmutable::parse(end($lote)['fecha'])->format('d/m/Y'),
            'marcos' => array_pad(
                array_map(fn (array $marco): array => array_diff_key($marco, ['fecha' => true]), $lote),
                (int) ceil(count($lote) / self::POR_RENGLON) * self::POR_RENGLON,
                null,
            ),
        ], $hojas, array_keys($hojas));
    }
}
