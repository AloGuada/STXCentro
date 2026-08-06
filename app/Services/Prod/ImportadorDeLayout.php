<?php

namespace App\Services\Prod;

use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Categoria;
use App\Models\Prod\Pieza;
use Illuminate\Support\Facades\DB;

/**
 * Carga el layout de planta sobre un catálogo.
 *
 * El archivo es una lista de **piezas físicas**: un renglón por pieza,
 * repitiendo marca y lote tantas veces como piezas tenga el modelo. De ahí salen
 * los dos niveles de la jerarquía: la marca se escribe una vez con los datos del
 * modelo (descripción, peso, longitud, categoría) y cada renglón entra como una
 * pieza suya.
 *
 * Columnas del layout vigente:
 * `QR, MARCA, DESCRIPCION, CATEGORIA, QS, CANTIDAD, PESO KG, AREA, LONGITUD MM, LOTE`.
 * El orden no importa —se emparejan por nombre— y los espacios del encabezado se
 * ignoran, así que `PESO KG` y `PESOKG` son la misma columna. Los layouts viejos
 * siguen cargando: sin QR se usa el QS como identificador, y una columna ETAPA
 * se lee como LOTE, que es el nombre nuevo del mismo dato.
 *
 * La cantidad de la marca **se cuenta**: son las piezas que le quedan colgando
 * en el catálogo, no la columna CANTIDAD. Esa columna sólo sirve para avisar
 * cuando el layout declara más piezas de las que mandó.
 *
 * La marca se empareja por `(catálogo, marca, lote)` y la pieza por
 * `(catálogo, QR)`. El QR no se usa para emparejar la marca a propósito: si
 * planta reetiqueta, la pieza se mueve pero el modelo no se duplica.
 */
class ImportadorDeLayout
{
    /**
     * @return array{marcas: int, piezas: int, avisos: list<string>}
     */
    public function importar(Catalogo $catalogo, string $ruta): array
    {
        ['filas' => $filas, 'avisos' => $avisos] = $this->leer($ruta);

        if ($filas === []) {
            return ['marcas' => 0, 'piezas' => 0, 'avisos' => $avisos];
        }

        $categorias = [];
        $marcasEscritas = 0;
        $piezasEscritas = 0;

        DB::transaction(function () use ($catalogo, $filas, &$categorias, &$marcasEscritas, &$piezasEscritas): void {
            foreach ($filas as $modelo) {
                $categoriaId = null;
                if ($modelo['categoria'] !== '') {
                    $categoriaId = $categorias[$modelo['categoria']] ??= Categoria::firstOrCreate(['nombre' => $modelo['categoria']])->id;
                }

                $marca = Concepto::updateOrCreate(
                    [
                        'catalogo_id' => $catalogo->id,
                        'marca' => $modelo['marca'],
                        'lote' => $modelo['lote'],
                    ],
                    [
                        'obra_id' => $catalogo->obra_id,
                        'descripcion' => $modelo['descripcion'],
                        'categoria_id' => $categoriaId,
                        'peso_unitario' => $modelo['peso_unitario'],
                        'longitud' => $modelo['longitud'],
                    ],
                );
                $marcasEscritas++;

                foreach ($modelo['piezas'] as $pieza) {
                    Pieza::updateOrCreate(
                        ['catalogo_id' => $catalogo->id, 'qr' => $pieza['qr']],
                        ['concepto_id' => $marca->id, 'qs' => $pieza['qs']],
                    );
                    $piezasEscritas++;
                }

                // Se cuenta despues de escribir las piezas y sobre las que tiene
                // la marca en el catalogo, no sobre las del archivo: asi un
                // layout parcial suma sus piezas en vez de borrar la cuenta previa.
                $marca->update(['cantidad' => $marca->piezas()->count()]);
            }
        });

        return [
            'marcas' => $marcasEscritas,
            'piezas' => $piezasEscritas,
            'avisos' => [...$avisos, ...$this->avisosDeCantidad($filas)],
        ];
    }

    /**
     * Agrupa los renglones del archivo por modelo. Los datos del modelo se toman
     * del primer renglón que lo trae; los siguientes sólo aportan su pieza.
     *
     * @return array{filas: array<string, array{marca: string, lote: ?string, descripcion: string, categoria: string, cantidad_declarada: int, peso_unitario: float, longitud: int, piezas: list<array{qr: string, qs: ?string}>}>, avisos: list<string>}
     */
    private function leer(string $ruta): array
    {
        $handle = fopen($ruta, 'r');

        if ($handle === false) {
            return ['filas' => [], 'avisos' => ['No se pudo leer el archivo.']];
        }

        $header = array_map(
            fn ($col) => $this->normalizarEncabezado((string) $col),
            fgetcsv($handle, 0, ',', '"', '') ?: [],
        );

        $modelos = [];
        $avisos = [];
        $qrVistos = [];
        $sinIdentificador = 0;
        $linea = 1;

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $linea++;

            if (count($row) < count($header)) {
                continue;
            }

            $data = array_combine($header, $row);

            $marca = trim((string) ($data['MARCA'] ?? ''));
            if ($marca === '') {
                continue;
            }

            $cantidad = (int) str_replace(',', '', (string) ($data['CANTIDAD'] ?? ''));
            $peso = (float) str_replace(',', '', (string) ($data['PESOKG'] ?? ''));

            // Renglones de resumen al pie del layout: sin cantidad ni peso reales.
            if ($cantidad < 1 && $peso <= 0) {
                continue;
            }

            // LOTE es el nombre nuevo de lo que el layout viejo llamaba ETAPA.
            $lote = Concepto::normalizarLote($data['LOTE'] ?? $data['ETAPA'] ?? null);
            $clave = Concepto::claveDeModelo($marca, $lote);

            $modelos[$clave] ??= [
                'marca' => $marca,
                'lote' => $lote,
                'descripcion' => trim((string) ($data['DESCRIPCION'] ?? $data['DESCRIPCIÓN'] ?? '')),
                'categoria' => trim((string) ($data['CATEGORIA'] ?? '')),
                'cantidad_declarada' => max($cantidad, 1),
                'peso_unitario' => $peso,
                'longitud' => (int) round((float) str_replace(',', '', (string) ($data['LONGITUDMM'] ?? '0'))),
                'piezas' => [],
            ];

            $qs = trim((string) ($data['QS'] ?? ''));
            // Sin QR se cae al QS, que es como se identificaba antes; así un
            // layout de los viejos sigue cargando sin tocar nada.
            $qr = trim((string) ($data['QR'] ?? '')) ?: $qs;

            if ($qr === '') {
                $sinIdentificador++;

                continue;
            }

            // Un QR repetido en el archivo apuntaria a dos modelos distintos y el
            // unique lo rechazaria a media carga; mejor avisarlo y quedarse con
            // la primera aparicion.
            if (isset($qrVistos[$qr])) {
                $avisos[] = "El QR {$qr} viene repetido (linea {$linea}); se tomo su primera aparicion.";

                continue;
            }

            $qrVistos[$qr] = true;
            $modelos[$clave]['piezas'][] = ['qr' => $qr, 'qs' => $qs === '' ? null : $qs];
        }

        fclose($handle);

        if ($sinIdentificador > 0) {
            $avisos[] = "{$sinIdentificador} renglon(es) sin QR ni QS se ignoraron: el layout debe identificar cada pieza.";
        }

        return ['filas' => $modelos, 'avisos' => $avisos];
    }

    /**
     * El layout dice cuántas piezas tiene el modelo en cada renglón. Manda el
     * conteo de piezas —es lo que existe y contra lo que se paga—, pero si la
     * columna CANTIDAD dice otra cosa se avisa: normalmente significa que el
     * archivo vino incompleto y el tope quedaría corto sin que nadie lo note.
     *
     * @param  array<string, array{marca: string, lote: ?string, cantidad_declarada: int, piezas: list<array{qr: string, qs: ?string}>}>  $modelos
     * @return list<string>
     */
    private function avisosDeCantidad(array $modelos): array
    {
        $avisos = [];

        foreach ($modelos as $modelo) {
            $llegaron = count($modelo['piezas']);

            if ($llegaron === $modelo['cantidad_declarada']) {
                continue;
            }

            $etiqueta = Concepto::etiquetaDeModelo($modelo['marca'], $modelo['lote']);
            $avisos[] = "{$etiqueta}: el layout dice {$modelo['cantidad_declarada']} pieza(s) y llegaron {$llegaron}; la cantidad se cuenta de las piezas.";
        }

        return $avisos;
    }

    /**
     * Quita el BOM, los espacios (incluidos los de en medio) y los acentos que
     * estorban, para que `PESO KG`, `PESOKG` y `peso kg` sean la misma columna.
     */
    private function normalizarEncabezado(string $columna): string
    {
        $sinBom = preg_replace('/^\xEF\xBB\xBF/', '', $columna) ?? $columna;

        return preg_replace('/\s+/u', '', mb_strtoupper(trim($sinBom))) ?? $sinBom;
    }
}
