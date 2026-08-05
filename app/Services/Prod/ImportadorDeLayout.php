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
 * El archivo es una lista de **piezas físicas**: un renglón por QS, repitiendo
 * marca y etapa tantas veces como piezas tenga el modelo. De ahí salen los dos
 * niveles de la jerarquía: la marca se escribe una vez con los datos del modelo
 * (descripción, peso, longitud, categoría) y cada QS entra como una pieza suya.
 *
 * La marca se empareja por `(catálogo, marca, etapa)` y la pieza por
 * `(catálogo, QS)`. El QS no se usa para emparejar la marca a propósito: si
 * planta renumera, la pieza se mueve pero el modelo no se duplica.
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
                        'etapa' => $modelo['etapa'],
                    ],
                    [
                        'obra_id' => $catalogo->obra_id,
                        'descripcion' => $modelo['descripcion'],
                        'categoria_id' => $categoriaId,
                        'cantidad' => $modelo['cantidad'],
                        'peso_unitario' => $modelo['peso_unitario'],
                        'longitud' => $modelo['longitud'],
                    ],
                );
                $marcasEscritas++;

                foreach ($modelo['qs'] as $qs) {
                    Pieza::updateOrCreate(
                        ['catalogo_id' => $catalogo->id, 'qs' => $qs],
                        ['concepto_id' => $marca->id],
                    );
                    $piezasEscritas++;
                }
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
     * del primer renglón que lo trae; los siguientes sólo aportan su QS.
     *
     * @return array{filas: array<string, array{marca: string, etapa: ?string, descripcion: string, categoria: string, cantidad: int, peso_unitario: float, longitud: int, qs: list<string>}>, avisos: list<string>}
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
        $qsVistos = [];
        $sinQs = 0;
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

            $etapa = Concepto::normalizarEtapa($data['ETAPA'] ?? null);
            $clave = Concepto::claveDeModelo($marca, $etapa);

            $modelos[$clave] ??= [
                'marca' => $marca,
                'etapa' => $etapa,
                'descripcion' => trim((string) ($data['DESCRIPCION'] ?? $data['DESCRIPCIÓN'] ?? '')),
                'categoria' => trim((string) ($data['CATEGORIA'] ?? '')),
                'cantidad' => max($cantidad, 1),
                'peso_unitario' => $peso,
                'longitud' => (int) round((float) str_replace(',', '', (string) ($data['LONGITUDMM'] ?? '0'))),
                'qs' => [],
            ];

            $qs = trim((string) ($data['QS'] ?? ''));

            if ($qs === '') {
                $sinQs++;

                continue;
            }

            // Un QS repetido en el archivo apuntaria a dos modelos distintos y el
            // unique lo rechazaria a media carga; mejor avisarlo y quedarse con
            // la primera aparicion.
            if (isset($qsVistos[$qs])) {
                $avisos[] = "El QS {$qs} viene repetido (linea {$linea}); se tomo su primera aparicion.";

                continue;
            }

            $qsVistos[$qs] = true;
            $modelos[$clave]['qs'][] = $qs;
        }

        fclose($handle);

        if ($sinQs > 0) {
            $avisos[] = "{$sinQs} renglon(es) sin QS se ignoraron: el layout debe traer el QS de cada pieza.";
        }

        return ['filas' => $modelos, 'avisos' => $avisos];
    }

    /**
     * El layout dice cuántas piezas tiene el modelo en cada renglón. Si no
     * cuadra con los QS que llegaron, es que el archivo viene incompleto: se
     * carga igual pero se avisa, porque el tope se calcula sobre las piezas que
     * existen y quedaría corto sin que nadie lo note.
     *
     * @param  array<string, array{marca: string, etapa: ?string, cantidad: int, qs: list<string>}>  $modelos
     * @return list<string>
     */
    private function avisosDeCantidad(array $modelos): array
    {
        $avisos = [];

        foreach ($modelos as $modelo) {
            $llegaron = count($modelo['qs']);

            if ($llegaron === $modelo['cantidad']) {
                continue;
            }

            $etiqueta = Concepto::etiquetaDeModelo($modelo['marca'], $modelo['etapa']);
            $avisos[] = "{$etiqueta}: el layout dice {$modelo['cantidad']} pieza(s) y llegaron {$llegaron} QS.";
        }

        return $avisos;
    }

    /** Quita el BOM y normaliza a mayúsculas sin espacios de más. */
    private function normalizarEncabezado(string $columna): string
    {
        $sinBom = preg_replace('/^\xEF\xBB\xBF/', '', $columna) ?? $columna;

        return mb_strtoupper(trim($sinBom));
    }
}
