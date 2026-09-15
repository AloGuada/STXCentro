<?php

namespace App\Services\Prod;

use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Categoria;
use App\Models\Prod\Pieza;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
            $indice = $this->indiceDeMarcas($catalogo);
            $porEscribir = [];
            $nuevas = [];
            $tocadas = [];

            foreach ($filas as $modelo) {
                $categoriaId = null;
                if ($modelo['categoria'] !== '') {
                    $categoriaId = $categorias[$modelo['categoria']] ??= $this->categoria($modelo['categoria']);
                }

                $marca = $this->marcaDelCatalogo($indice, $modelo['marca'], $modelo['lote'], $avisosDeEmpate);

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
                ]);

                // La que ya existía se guarda aquí, y `save()` no escribe nada si
                // el renglón viene igual que la última vez: reimportar el mismo
                // layout no toca la base. Las nuevas van al insert en bloque.
                if ($marca->exists) {
                    $marca->save();
                } else {
                    $nuevas[] = $marca;
                }

                $marcasEscritas++;
                $tocadas[] = $marca;

                foreach ($modelo['piezas'] as $pieza) {
                    $porEscribir[] = [
                        'marca' => $marca,
                        'qr' => $pieza['qr'],
                        'qs' => $pieza['qs'],
                        'correlativo' => $pieza['correlativo'],
                    ];
                    $piezasEscritas++;
                }
            }

            $this->insertarMarcasNuevas($catalogo, $nuevas);
            $this->escribirPiezas($catalogo, $porEscribir);

            // Se cuenta despues de escribir las piezas y sobre las que tiene
            // la marca en el catalogo, no sobre las del archivo: asi un
            // layout parcial suma sus piezas en vez de borrar la cuenta previa.
            $this->recontarPiezas(array_map(fn (Concepto $marca): int => (int) $marca->id, $tocadas));
        });

        return [
            'marcas' => $marcasEscritas,
            'piezas' => $piezasEscritas,
            'avisos' => [...$avisos, ...$avisosDeEmpate, ...$this->avisosDeCantidad($filas)],
        ];
    }

    /**
     * Las marcas que ya tiene el catálogo, agrupadas por nombre de marca.
     *
     * Emparejar contra la base marca por marca eran tres consultas por renglón
     * del layout —el grueso del tiempo de un import grande, y lo que lo llevaba
     * al timeout—. Aquí se leen todas de una vez y el emparejamiento se resuelve
     * en memoria. El índice se va actualizando con las marcas nuevas para que
     * las decisiones que dependen de lo ya escrito (ver `marcaDelCatalogo`)
     * sigan viendo lo mismo que veían cuando preguntaban a la base.
     *
     * @return array<string, list<Concepto>>
     */
    private function indiceDeMarcas(Catalogo $catalogo): array
    {
        $indice = [];

        foreach (Concepto::where('catalogo_id', $catalogo->id)->get() as $marca) {
            $indice[trim((string) $marca->marca)][] = $marca;
        }

        return $indice;
    }

    /**
     * Da de alta en una sola sentencia las marcas que el layout trae por primera
     * vez y les devuelve su id, que es lo que cuelga a sus piezas.
     *
     * El id se recupera emparejando por el par `(marca, lote)` tal cual, que es
     * el mismo con el que se decidió que la marca no existía: si no había fila
     * con ese par antes del insert, la única que hay después es la recién
     * escrita.
     *
     * @param  list<Concepto>  $nuevas
     */
    private function insertarMarcasNuevas(Catalogo $catalogo, array $nuevas): void
    {
        if ($nuevas === []) {
            return;
        }

        $ahora = now();

        foreach (array_chunk($nuevas, 500) as $bloque) {
            Concepto::insert(array_map(
                fn (Concepto $marca): array => $marca->getAttributes() + ['created_at' => $ahora, 'updated_at' => $ahora],
                $bloque,
            ));
        }

        $ids = Concepto::query()
            ->where('catalogo_id', $catalogo->id)
            ->whereIn('marca', array_values(array_unique(array_map(
                fn (Concepto $marca): string => (string) $marca->marca,
                $nuevas,
            ))))
            ->get(['id', 'marca', 'lote'])
            ->mapWithKeys(fn (Concepto $marca): array => [$this->parDeMarca($marca->marca, $marca->lote) => (int) $marca->id]);

        foreach ($nuevas as $marca) {
            $id = $ids[$this->parDeMarca($marca->marca, $marca->lote)] ?? null;

            if ($id === null) {
                throw new RuntimeException("No se pudo recuperar la marca {$marca->etiquetaModelo()} recién insertada.");
            }

            $marca->id = $id;
            $marca->exists = true;
            $marca->syncOriginal();
        }
    }

    /** El par `(marca, lote)` sin normalizar, para emparejar contra la base. */
    private function parDeMarca(?string $marca, ?string $lote): string
    {
        return $marca."\0".($lote ?? "\0nulo");
    }

    /**
     * Escribe las piezas del layout en bloque.
     *
     * Va en `upsert` y no en `updateOrCreate` por pieza a propósito. Cada
     * `updateOrCreate` abre un SAVEPOINT dentro de la transacción del import, y
     * en PostgreSQL cada subtransacción se queda con un lock hasta el commit:
     * con un layout de miles de renglones se agota el pool de locks y revienta
     * con «out of shared memory / max_locks_per_transaction». Un `upsert` por
     * bloque es una sola sentencia, sin savepoints y sin ida y vuelta por fila.
     *
     * El `ON CONFLICT` se apoya en el unique `(catalogo_id, qr)`; el lector ya
     * garantiza que un QR no venga dos veces en el mismo archivo, que Postgres
     * tampoco deja tocar la misma fila dos veces en la misma sentencia.
     *
     * @param  list<array{marca: Concepto, qr: string, qs: ?string, correlativo: ?int}>  $piezas
     */
    private function escribirPiezas(Catalogo $catalogo, array $piezas): void
    {
        foreach (array_chunk($piezas, 500) as $bloque) {
            Pieza::upsert(
                array_map(fn (array $pieza): array => [
                    'catalogo_id' => $catalogo->id,
                    'concepto_id' => (int) $pieza['marca']->id,
                    'qr' => $pieza['qr'],
                    'qs' => $pieza['qs'],
                    'correlativo' => $pieza['correlativo'],
                ], $bloque),
                ['catalogo_id', 'qr'],
                [
                    'concepto_id',
                    // Lo que el archivo no trae no borra lo que ya había: el
                    // layout vigente ya no manda QS y las piezas viejas lo
                    // conservan, que el CSV de avance todavía empareja por él.
                    // `excluded` es la fila que venía a insertarse, en
                    // PostgreSQL y en SQLite por igual.
                    'qs' => DB::raw('COALESCE(excluded.qs, prod_piezas.qs)'),
                    'correlativo' => DB::raw('COALESCE(excluded.correlativo, prod_piezas.correlativo)'),
                ],
            );
        }
    }

    /**
     * Deja en cada marca tocada la cuenta de piezas que le quedaron colgando.
     *
     * Se hace al final y en bloque por lo mismo que las piezas: un `count()` y
     * un `update()` por marca son dos viajes por renglón del layout. Las marcas
     * se agrupan por cuenta, que casi todas comparten el mismo número.
     *
     * @param  array<int, int>  $marcaIds
     */
    private function recontarPiezas(array $marcaIds): void
    {
        foreach (array_chunk(array_values($marcaIds), 500) as $bloque) {
            $conteos = Pieza::query()
                ->whereIn('concepto_id', $bloque)
                ->groupBy('concepto_id')
                ->selectRaw('concepto_id, count(*) as total')
                ->pluck('total', 'concepto_id');

            $porCantidad = [];

            foreach ($bloque as $id) {
                $porCantidad[(int) ($conteos[$id] ?? 0)][] = $id;
            }

            foreach ($porCantidad as $cantidad => $ids) {
                Concepto::whereIn('id', $ids)->update(['cantidad' => $cantidad]);
            }
        }
    }

    /**
     * La categoría del layout, creándola si es nueva. Se evita `firstOrCreate`
     * por la misma razón que en las piezas: abre un savepoint por categoría
     * nueva dentro de la transacción del import.
     */
    private function categoria(string $nombre): int
    {
        $existente = Categoria::where('nombre', $nombre)->value('id');

        return $existente ?? Categoria::create(['nombre' => $nombre])->id;
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
     * @param  array<string, list<Concepto>>  $indice
     * @param  list<string>  $avisos
     */
    private function marcaDelCatalogo(array &$indice, string $marca, ?string $lote, array &$avisos): Concepto
    {
        $delCatalogo = $indice[$marca] ?? [];

        foreach ($delCatalogo as $candidata) {
            if ($candidata->lote === $lote) {
                return $candidata;
            }
        }

        // El layout trae lote y la marca ya existía sin él: es la misma, de una
        // carga anterior. Se le asigna el lote en vez de duplicarla.
        if ($lote !== null) {
            $sinLote = array_values(array_filter($delCatalogo, fn (Concepto $c): bool => $c->lote === null));

            if (count($sinLote) === 1) {
                $avisos[] = "{$marca}: ya estaba en el catálogo sin lote; se le asignó el lote {$lote} en vez de duplicar la marca.";

                return $sinLote[0];
            }

            return $this->marcaNueva($indice, $marca);
        }

        // Al revés: el layout no distingue lotes y la marca vive en uno solo. Se
        // reusa ese, que si no quedarían la marca con lote y su gemela sin él.
        if (count($delCatalogo) === 1) {
            $avisos[] = "{$marca}: el layout no trae lote y la marca ya estaba en el lote {$delCatalogo[0]->lote}; se actualizó esa en vez de duplicarla.";

            return $delCatalogo[0];
        }

        return $this->marcaNueva($indice, $marca);
    }

    /**
     * Una marca que el catálogo no tenía. Entra al índice de una vez —todavía
     * sin id, que llega en el insert en bloque— porque los renglones que vengan
     * después tienen que verla igual que la veían en la base cuando cada marca
     * se guardaba en el acto.
     *
     * @param  array<string, list<Concepto>>  $indice
     */
    private function marcaNueva(array &$indice, string $marca): Concepto
    {
        $nueva = new Concepto;
        $indice[$marca][] = $nueva;

        return $nueva;
    }

    /**
     * Agrupa los renglones del archivo por modelo. Los datos del modelo se toman
     * del primer renglón que lo trae; los siguientes sólo aportan su pieza.
     *
     * @return array{filas: array<string, array{marca: string, lote: ?string, descripcion: string, categoria: string, cantidad_declarada: int, peso_unitario: float, longitud: int, piezas: list<array{qr: string, qs: ?string, correlativo: ?int}>}>, avisos: list<string>}
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
                // El layout vigente la llama CATEGORIA QS; es una etiqueta de
                // texto, no el número de QS. El nombre viejo sigue entrando.
                'categoria' => trim((string) ($data['CATEGORIAQS'] ?? $data['CATEGORIA'] ?? '')),
                'cantidad_declarada' => max($cantidad, 1),
                'peso_unitario' => $peso,
                'longitud' => (int) round((float) str_replace(',', '', (string) ($data['LONGITUDMM'] ?? '0'))),
                'piezas' => [],
            ];

            // El layout vigente ya no trae QS; se sigue leyendo por los viejos.
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
            $modelos[$clave]['piezas'][] = [
                'qr' => $qr,
                'qs' => $qs === '' ? null : $qs,
                'correlativo' => $this->correlativo($data['CORRELATIVO'] ?? null),
            ];
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
        'QR', 'MARCA', 'DESCRIPCION', 'DESCRIPCIÓN', 'CATEGORIAQS', 'CORRELATIVO',
        'CATEGORIA', 'QS', 'CANTIDAD', 'PESOKG', 'AREA', 'LONGITUDMM', 'LOTE', 'ETAPA',
    ];

    /**
     * La numeración que planta le da a la pieza según su QR. Vacío o no
     * numérico es «sin correlativo», no cero.
     */
    private function correlativo(mixed $valor): ?int
    {
        $texto = trim((string) $valor);

        return $texto !== '' && is_numeric($texto) ? (int) round((float) $texto) : null;
    }

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
     * una sola pieza si no se puede: `PESOKGAREA` → `[PESOKG, AREA]`.
     * `CATEGORIAQS` es una columna de verdad y está en la lista antes que
     * `CATEGORIA`, así que no se lee como dos pegadas.
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
