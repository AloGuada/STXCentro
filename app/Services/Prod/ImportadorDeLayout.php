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
        $avisosDeEmpate = [];

        DB::transaction(function () use ($catalogo, $filas, &$categorias, &$marcasEscritas, &$piezasEscritas, &$avisosDeEmpate): void {
            foreach ($filas as $modelo) {
                $categoriaId = null;
                if ($modelo['categoria'] !== '') {
                    $categoriaId = $categorias[$modelo['categoria']] ??= Categoria::firstOrCreate(['nombre' => $modelo['categoria']])->id;
                }

                $marca = $this->marcaDelCatalogo($catalogo, $modelo['marca'], $modelo['lote'], $avisosDeEmpate);

                $marca->fill([
                    'catalogo_id' => $catalogo->id,
                    'marca' => $modelo['marca'],
                    // Al reusar una marca vieja sin lote se le pone el del layout;
                    // si el layout no trae lote, se respeta el que ya tenía.
                    'lote' => $modelo['lote'] ?? $marca->lote,
                    'obra_id' => $catalogo->obra_id,
                    'descripcion' => $modelo['descripcion'],
                    'categoria_id' => $categoriaId,
                    'peso_unitario' => $modelo['peso_unitario'],
                    'longitud' => $modelo['longitud'],
                ])->save();
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
            'avisos' => [...$avisos, ...$avisosDeEmpate, ...$this->avisosDeCantidad($filas)],
        ];
    }

    /**
     * La marca del catálogo sobre la que se escribe el modelo del layout.
     *
     * La identidad es `(catálogo, marca, lote)`, pero emparejar sólo por ahí
     * duplica las marcas cuando el catálogo se cargó antes con un layout que no
     * traía lote: la misma marca entraría otra vez, la vieja se quedaría sin
     * piezas y con su `cantidad` inflada, y en pantalla se ve dos veces. Así que
     * cuando no hay coincidencia exacta y sólo hay una candidata evidente, se
     * reusa esa fila en vez de crear una gemela.
     *
     * @param  list<string>  $avisos
     */
    private function marcaDelCatalogo(Catalogo $catalogo, string $marca, ?string $lote, array &$avisos): Concepto
    {
        $delCatalogo = fn () => Concepto::query()
            ->where('catalogo_id', $catalogo->id)
            ->where('marca', $marca);

        $exacta = $delCatalogo()->where('lote', $lote)->first();

        if ($exacta !== null) {
            return $exacta;
        }

        // El layout trae lote y la marca ya existía sin él: es la misma, de una
        // carga anterior. Se le asigna el lote en vez de duplicarla.
        if ($lote !== null) {
            $sinLote = $delCatalogo()->whereNull('lote')->get();

            if ($sinLote->count() === 1) {
                $avisos[] = "{$marca}: ya estaba en el catálogo sin lote; se le asignó el lote {$lote} en vez de duplicar la marca.";

                return $sinLote->first();
            }

            return new Concepto;
        }

        // Al revés: el layout no distingue lotes y la marca vive en uno solo. Se
        // reusa ese, que si no quedarían la marca con lote y su gemela sin él.
        $unica = $delCatalogo()->get();

        if ($unica->count() === 1) {
            $avisos[] = "{$marca}: el layout no trae lote y la marca ya estaba en el lote {$unica->first()->lote}; se actualizó esa en vez de duplicarla.";

            return $unica->first();
        }

        return new Concepto;
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
        $avisos = $this->avisosDeEncabezado($header);
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
     * Columnas que el layout sabe leer, ya normalizadas.
     *
     * @var list<string>
     */
    private const COLUMNAS = [
        'QR', 'MARCA', 'DESCRIPCION', 'DESCRIPCIÓN', 'CATEGORIA', 'QS',
        'CANTIDAD', 'PESOKG', 'AREA', 'LONGITUDMM', 'LOTE', 'ETAPA',
    ];

    /**
     * Avisa cuando el encabezado trae dos columnas pegadas en una sola celda
     * (`CATEGORIA QS`). Pasa cuando el archivo se exporta mal: el renglón de
     * datos también viene con una columna de menos, así que todo lo que sigue se
     * lee corrido y esos datos entran vacíos sin que nadie lo note.
     *
     * @param  list<string>  $header
     * @return list<string>
     */
    private function avisosDeEncabezado(array $header): array
    {
        $avisos = [];

        foreach ($header as $columna) {
            if ($columna === '' || in_array($columna, self::COLUMNAS, true)) {
                continue;
            }

            $partes = $this->partirEnColumnas($columna);

            if (count($partes) > 1) {
                $avisos[] = sprintf(
                    'El encabezado trae «%s» en una sola columna: son %s y el archivo viene con una columna de menos, así que no se cargaron. Vuelve a exportar el layout.',
                    $columna,
                    implode(' y ', $partes),
                );
            }
        }

        return $avisos;
    }

    /**
     * Parte un encabezado en las columnas conocidas que lo forman, o devuelve
     * una sola pieza si no se puede: `CATEGORIAQS` → `[CATEGORIA, QS]`.
     *
     * @return list<string>
     */
    private function partirEnColumnas(string $columna): array
    {
        foreach (self::COLUMNAS as $conocida) {
            if (! str_starts_with($columna, $conocida)) {
                continue;
            }

            $resto = substr($columna, strlen($conocida));

            if ($resto === '') {
                return [$conocida];
            }

            $partesDelResto = $this->partirEnColumnas($resto);

            if ($partesDelResto !== [$resto]) {
                return [$conocida, ...$partesDelResto];
            }

            if (in_array($resto, self::COLUMNAS, true)) {
                return [$conocida, $resto];
            }
        }

        return [$columna];
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
