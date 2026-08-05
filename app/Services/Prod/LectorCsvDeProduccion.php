<?php

namespace App\Services\Prod;

/**
 * Normaliza el CSV de captura de producción a renglones de una pieza.
 *
 * Acepta dos formatos:
 *
 *  1. **Export de avance** (el que sale del sistema de planta): trae `Proceso`
 *     con el número de evento, `Ubicacion` y el `QS` de la pieza. El evento dice
 *     qué proceso se paga (75 soldadura, 85 pintura) y el QS dice exactamente
 *     qué pieza: no hay que adivinar nada por marca.
 *  2. **Captura a mano**: columnas GRUPO, QS y opcionalmente PROCESO y
 *     PORCENTAJE.
 *
 * El lector no toca la base: sólo dice a qué ubicación o grupo apunta cada
 * renglón y con qué evento o proceso viene. Resolver la pieza y el proceso
 * contra el catálogo es responsabilidad de quien importa.
 */
class LectorCsvDeProduccion
{
    public const FORMATO_EXPORT = 'export';

    public const FORMATO_SIMPLE = 'simple';

    /**
     * @return array{formato: string, filas: list<array{referencia: string, ubicacion: ?string, grupo: ?string, qs: string, evento: ?string, proceso: ?string, porcentaje: float}>}
     */
    public function leer(string $ruta): array
    {
        $handle = fopen($ruta, 'r');

        if ($handle === false) {
            return ['formato' => self::FORMATO_SIMPLE, 'filas' => []];
        }

        $encabezado = array_map(
            fn ($col) => $this->normalizar((string) $col),
            $this->siguiente($handle) ?: [],
        );

        $esExport = in_array('PROCESO', $encabezado, true) && in_array('UBICACION', $encabezado, true);

        $filas = $esExport
            ? $this->leerExport($handle, $encabezado)
            : $this->leerSimple($handle, $encabezado);

        fclose($handle);

        return [
            'formato' => $esExport ? self::FORMATO_EXPORT : self::FORMATO_SIMPLE,
            'filas' => $filas,
        ];
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $encabezado
     * @return list<array{referencia: string, ubicacion: ?string, grupo: ?string, qs: string, evento: ?string, proceso: ?string, porcentaje: float}>
     */
    private function leerExport($handle, array $encabezado): array
    {
        $indices = array_flip($encabezado);
        $filas = [];
        $vistos = [];

        while (($row = $this->siguiente($handle)) !== false) {
            if (count($row) < count($encabezado)) {
                continue;
            }

            $evento = $this->numeroDeEvento(trim((string) ($row[$indices['PROCESO']] ?? '')));
            $ubicacion = trim((string) ($row[$indices['UBICACION']] ?? ''));
            $qs = trim((string) ($row[$indices['QS']] ?? ''));

            if ($evento === null || $ubicacion === '' || $qs === '') {
                continue;
            }

            // Una pieza es una pieza: si el mismo QS viene dos veces en el mismo
            // evento, es el mismo trabajo reportado dos veces, no el doble.
            $clave = $qs.'|'.$evento;

            if (isset($vistos[$clave])) {
                continue;
            }

            $vistos[$clave] = true;

            $filas[] = [
                'referencia' => "\"{$ubicacion}\" / QS {$qs}",
                'ubicacion' => $ubicacion,
                'grupo' => null,
                'qs' => $qs,
                'evento' => $evento,
                'proceso' => null,
                'porcentaje' => 100.0,
            ];
        }

        return $filas;
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $encabezado
     * @return list<array{referencia: string, ubicacion: ?string, grupo: ?string, qs: string, evento: ?string, proceso: ?string, porcentaje: float}>
     */
    private function leerSimple($handle, array $encabezado): array
    {
        $filas = [];
        $linea = 1;

        while (($row = $this->siguiente($handle)) !== false) {
            $linea++;

            if (count($row) < count($encabezado)) {
                continue;
            }

            $data = array_combine($encabezado, $row);
            $grupo = trim((string) ($data['GRUPO'] ?? ''));
            $qs = trim((string) ($data['QS'] ?? ''));

            if ($grupo === '' && $qs === '') {
                continue;
            }

            $porcentaje = (float) str_replace('%', '', trim((string) ($data['PORCENTAJE'] ?? '100'))) ?: 100.0;
            $proceso = trim((string) ($data['PROCESO'] ?? ''));

            $filas[] = [
                'referencia' => "Linea {$linea}",
                'ubicacion' => null,
                'grupo' => $grupo,
                'qs' => $qs,
                'evento' => null,
                'proceso' => $proceso === '' ? null : $proceso,
                'porcentaje' => $porcentaje,
            ];
        }

        return $filas;
    }

    /**
     * Lee un renglón. El escape va vacío a propósito: es el comportamiento RFC
     * 4180 (sin escapes con diagonal invertida) y desde PHP 8.4 el parámetro es
     * obligatorio.
     *
     * @param  resource  $handle
     * @return list<string|null>|false
     */
    private function siguiente($handle): array|false
    {
        return fgetcsv($handle, 0, ',', '"', '');
    }

    /** El proceso viene como "75 Soldadura": basta el número de evento. */
    private function numeroDeEvento(string $proceso): ?string
    {
        return preg_match('/^\s*(\d+)/', $proceso, $m) === 1 ? $m[1] : null;
    }

    /** Compara sin acentos, mayúsculas ni espacios de más. */
    public function normalizar(string $valor): string
    {
        $sinBom = preg_replace('/^\xEF\xBB\xBF/', '', $valor) ?? $valor;
        $sinAcentos = strtr(mb_strtoupper(trim($sinBom)), [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);

        return preg_replace('/\s+/', ' ', $sinAcentos) ?? $sinAcentos;
    }
}
