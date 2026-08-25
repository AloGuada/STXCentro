<?php

namespace App\Services\Prod;

/**
 * Normaliza el CSV de captura de producción a renglones de una pieza.
 *
 * Acepta dos formatos:
 *
 *  1. **Export de avance** (el que sale del sistema de planta): trae `Proceso`
 *     con el número de evento y `Ubicacion`. El evento dice qué proceso se paga
 *     (75 soldadura, 85 pintura).
 *  2. **Captura a mano**: columnas GRUPO y opcionalmente PROCESO y PORCENTAJE.
 *
 * Para decir de qué pieza habla el renglón, los dos aceptan tres columnas y
 * ninguna es obligatoria por sí sola. Mandan en este orden:
 *
 *  - `QR`: señala una pieza y nada más. Desde que el layout maneja lotes es el
 *    único identificador que no deja lugar a dudas.
 *  - `QS`: señala a las hermanas que comparten número de serie.
 *  - `MARCA`: señala el modelo completo. Es lo único que traen los exports que
 *    no numeran pieza, y ahí cada renglón vale una pieza del modelo.
 *
 * Con QS o marca el renglón no dice *cuál* pieza: eso lo decide quien importa,
 * tomando siempre la de QR más chico que todavía tenga cupo en ese proceso.
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
     * @return array{formato: string, filas: list<array{referencia: string, linea: int, ubicacion: ?string, grupo: ?string, qr: ?string, qs: string, marca: ?string, evento: ?string, proceso: ?string, porcentaje: float}>}
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
     * @return list<array{referencia: string, linea: int, ubicacion: ?string, grupo: ?string, qr: ?string, qs: string, marca: ?string, evento: ?string, proceso: ?string, porcentaje: float}>
     */
    private function leerExport($handle, array $encabezado): array
    {
        $indices = array_flip($encabezado);
        $filas = [];
        $vistos = [];
        $linea = 1;

        while (($row = $this->siguiente($handle)) !== false) {
            $linea++;

            if (count($row) < count($encabezado)) {
                continue;
            }

            $evento = $this->numeroDeEvento($this->columna($row, $indices, 'PROCESO'));
            $ubicacion = $this->columna($row, $indices, 'UBICACION');
            // De más preciso a menos: el QR señala una pieza, el QS un puñado de
            // hermanas y la marca todo el modelo. Los tres son opcionales porque
            // no todos los exports de planta traen los mismos.
            $qr = $this->columna($row, $indices, 'QR');
            $qs = $this->columna($row, $indices, 'QS');
            $marca = $this->columna($row, $indices, 'MARCA');

            if ($evento === null || $ubicacion === '' || ($qs === '' && $qr === '' && $marca === '')) {
                continue;
            }

            // Con QR la pieza es única, así que verla dos veces en el mismo
            // evento es el mismo trabajo reportado dos veces, no el doble.
            //
            // Sin QR no se puede afirmar eso: el QS se repite entre lotes, así
            // que tres renglones iguales pueden ser tres piezas distintas del
            // mismo modelo. Se dejan pasar y el tope decide cuántas caben.
            if ($qr !== '') {
                $clave = $qr.'|'.$evento;

                if (isset($vistos[$clave])) {
                    continue;
                }

                $vistos[$clave] = true;
            }

            $filas[] = [
                'referencia' => match (true) {
                    $qr !== '' => "\"{$ubicacion}\" / QR {$qr}",
                    $qs !== '' => "\"{$ubicacion}\" / QS {$qs} (linea {$linea})",
                    default => "\"{$ubicacion}\" / {$marca} (linea {$linea})",
                },
                'linea' => $linea,
                'ubicacion' => $ubicacion,
                'grupo' => null,
                'qr' => $qr === '' ? null : $qr,
                'qs' => $qs,
                'marca' => $marca === '' ? null : $marca,
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
     * @return list<array{referencia: string, linea: int, ubicacion: ?string, grupo: ?string, qr: ?string, qs: string, marca: ?string, evento: ?string, proceso: ?string, porcentaje: float}>
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
            $qr = trim((string) ($data['QR'] ?? ''));
            $marca = trim((string) ($data['MARCA'] ?? ''));

            if ($grupo === '' && $qs === '' && $qr === '' && $marca === '') {
                continue;
            }

            $porcentaje = (float) str_replace('%', '', trim((string) ($data['PORCENTAJE'] ?? '100'))) ?: 100.0;
            $proceso = trim((string) ($data['PROCESO'] ?? ''));

            $filas[] = [
                'referencia' => "Linea {$linea}",
                'linea' => $linea,
                'ubicacion' => null,
                'grupo' => $grupo,
                'qr' => $qr === '' ? null : $qr,
                'qs' => $qs,
                'marca' => $marca === '' ? null : $marca,
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

    /**
     * Una celda del renglón, o cadena vacía si el archivo no trae esa columna.
     *
     * Va por aquí todo lo que se lee del export: los archivos de planta no
     * siempre traen las mismas columnas, y tocar `$indices['QS']` cuando no
     * existe tumbaba el import entero con un "Undefined array key".
     *
     * @param  list<string|null>  $row
     * @param  array<string, int>  $indices
     */
    private function columna(array $row, array $indices, string $columna): string
    {
        if (! isset($indices[$columna])) {
            return '';
        }

        return trim((string) ($row[$indices[$columna]] ?? ''));
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
