<?php

namespace App\Services\Prod;

use App\Models\Concepto;

/**
 * Normaliza el CSV de captura de producción a renglones (grupo, marca, etapa,
 * cantidad).
 *
 * Acepta dos formatos:
 *
 *  1. **Export de avance** (el que sale del sistema de planta): trae una columna
 *     `Proceso` con el evento. Se toma sólo el evento 55 (soldadura), que es lo
 *     que se paga como destajo, y de ahí la Ubicación, la Marca, la Etapa y la
 *     Cantidad. Un mismo trío ubicación+marca+etapa puede venir en varios
 *     movimientos del día, así que se suman.
 *  2. **Captura a mano**: columnas GRUPO, MARCA, CANTIDAD y opcionalmente ETAPA
 *     y PORCENTAJE.
 *
 * `ETAPA` es opcional en ambos formatos porque los archivos viejos no la traen;
 * cuando falta, el renglón sale con etapa nula y quien importa decide qué hacer
 * si la marca está repetida en el catálogo.
 *
 * El resultado sólo dice a qué ubicación o grupo apunta cada renglón; resolverlo
 * contra el catálogo es responsabilidad de quien importa.
 */
class LectorCsvDeProduccion
{
    /** Evento del export que corresponde a la soldadura (lo que se paga). */
    public const EVENTO_DESTAJO = '55';

    public const FORMATO_EXPORT = 'export';

    public const FORMATO_SIMPLE = 'simple';

    /**
     * @return array{formato: string, filas: list<array{referencia: string, ubicacion: ?string, grupo: ?string, marca: string, etapa: ?string, cantidad: int, porcentaje: float}>}
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
     * @return list<array{referencia: string, ubicacion: ?string, grupo: ?string, marca: string, etapa: ?string, cantidad: int, porcentaje: float}>
     */
    private function leerExport($handle, array $encabezado): array
    {
        $indices = array_flip($encabezado);
        /** @var array<string, array{ubicacion: string, marca: string, etapa: ?string, cantidad: int}> $acumulado */
        $acumulado = [];

        while (($row = $this->siguiente($handle)) !== false) {
            if (count($row) < count($encabezado)) {
                continue;
            }

            $proceso = trim((string) ($row[$indices['PROCESO']] ?? ''));

            if (! $this->esEventoDeDestajo($proceso)) {
                continue;
            }

            $ubicacion = trim((string) ($row[$indices['UBICACION']] ?? ''));
            $marca = trim((string) ($row[$indices['MARCA']] ?? ''));
            $etapa = Concepto::normalizarEtapa(
                isset($indices['ETAPA']) ? (string) ($row[$indices['ETAPA']] ?? '') : null
            );
            $cantidad = (int) trim((string) ($row[$indices['CANTIDAD']] ?? '0'));

            if ($ubicacion === '' || $marca === '') {
                continue;
            }

            $clave = $this->normalizar($ubicacion).'|'.Concepto::claveDeModelo($marca, $etapa);

            if (isset($acumulado[$clave])) {
                $acumulado[$clave]['cantidad'] += $cantidad;

                continue;
            }

            $acumulado[$clave] = ['ubicacion' => $ubicacion, 'marca' => $marca, 'etapa' => $etapa, 'cantidad' => $cantidad];
        }

        return array_values(array_map(fn (array $fila) => [
            'referencia' => "\"{$fila['ubicacion']}\" / ".Concepto::etiquetaDeModelo($fila['marca'], $fila['etapa']),
            'ubicacion' => $fila['ubicacion'],
            'grupo' => null,
            'marca' => $fila['marca'],
            'etapa' => $fila['etapa'],
            'cantidad' => $fila['cantidad'],
            'porcentaje' => 100.0,
        ], $acumulado));
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $encabezado
     * @return list<array{referencia: string, ubicacion: ?string, grupo: ?string, marca: string, etapa: ?string, cantidad: int, porcentaje: float}>
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
            $marca = trim((string) ($data['MARCA'] ?? ''));

            if ($grupo === '' && $marca === '') {
                continue;
            }

            $porcentaje = (float) str_replace('%', '', trim((string) ($data['PORCENTAJE'] ?? '100'))) ?: 100.0;

            $filas[] = [
                'referencia' => "Linea {$linea}",
                'ubicacion' => null,
                'grupo' => $grupo,
                'marca' => $marca,
                'etapa' => Concepto::normalizarEtapa($data['ETAPA'] ?? null),
                'cantidad' => (int) trim((string) ($data['CANTIDAD'] ?? '0')),
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

    /** El proceso viene como "55 Soldadura": basta el número de evento. */
    private function esEventoDeDestajo(string $proceso): bool
    {
        return preg_match('/^\s*(\d+)/', $proceso, $m) === 1 && $m[1] === self::EVENTO_DESTAJO;
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
