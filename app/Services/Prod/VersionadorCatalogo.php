<?php

namespace App\Services\Prod;

use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\Pieza;
use Illuminate\Support\Facades\DB;

/**
 * Crea versiones nuevas del catálogo de piezas de una obra y compara dos
 * versiones entre sí.
 *
 * La versión anterior queda congelada: la producción capturada y las
 * liquidaciones cerradas siguen apuntando a sus piezas originales, así que
 * ningún número histórico se mueve al versionar.
 */
class VersionadorCatalogo
{
    /**
     * Copia el catálogo vigente de la obra a una versión nueva (que pasa a ser
     * la vigente) junto con sus asignaciones de grupo de precio.
     */
    public function nuevaVersion(Catalogo $catalogo, ?string $notas = null): Catalogo
    {
        return DB::transaction(function () use ($catalogo, $notas): Catalogo {
            $siguiente = (int) Catalogo::query()
                ->where('obra_id', $catalogo->obra_id)
                ->max('version') + 1;

            Catalogo::query()
                ->where('obra_id', $catalogo->obra_id)
                ->update(['vigente' => false]);

            $nueva = Catalogo::create([
                'obra_id' => $catalogo->obra_id,
                'catalogo_origen_id' => $catalogo->id,
                'nombre' => $catalogo->nombre,
                'version' => $siguiente,
                'vigente' => true,
                'notas' => $notas,
            ]);

            $this->copiarPiezas($catalogo, $nueva);

            return $nueva;
        });
    }

    /**
     * Diferencias entre dos versiones, emparejadas por modelo (marca + lote).
     * Emparejar sólo por marca juntaría piezas distintas cuando el catálogo
     * repite la marca en varios lotes de la obra.
     *
     * @return array{
     *     agregadas: list<array{marca: string, lote: ?string, descripcion: string}>,
     *     eliminadas: list<array{marca: string, lote: ?string, descripcion: string}>,
     *     modificadas: list<array{marca: string, lote: ?string, descripcion: string, cambios: list<array{campo: string, antes: mixed, despues: mixed}>}>,
     *     sin_cambios: int
     * }
     */
    public function comparar(Catalogo $base, Catalogo $contra): array
    {
        $piezasBase = $base->conceptos()->get()->keyBy(fn (Concepto $pieza) => $pieza->claveModelo());
        $piezasContra = $contra->conceptos()->get()->keyBy(fn (Concepto $pieza) => $pieza->claveModelo());

        $agregadas = [];
        $eliminadas = [];
        $modificadas = [];
        $sinCambios = 0;

        foreach ($piezasContra as $clave => $pieza) {
            if (! $piezasBase->has($clave)) {
                $agregadas[] = $this->resumenDePieza($pieza);

                continue;
            }

            $cambios = $this->cambiosEntrePiezas($piezasBase->get($clave), $pieza);

            if ($cambios === []) {
                $sinCambios++;

                continue;
            }

            $modificadas[] = [...$this->resumenDePieza($pieza), 'cambios' => $cambios];
        }

        foreach ($piezasBase as $clave => $pieza) {
            if (! $piezasContra->has($clave)) {
                $eliminadas[] = $this->resumenDePieza($pieza);
            }
        }

        return [
            'agregadas' => $agregadas,
            'eliminadas' => $eliminadas,
            'modificadas' => $modificadas,
            'sin_cambios' => $sinCambios,
        ];
    }

    /**
     * Duplica las piezas y arrastra sus grupos de precio, para no tener que
     * reasignar precios a mano en cada versión.
     */
    private function copiarPiezas(Catalogo $origen, Catalogo $destino): void
    {
        $preciosPorConcepto = GrupoPrecioConcepto::query()
            ->whereIn('concepto_id', $origen->conceptos()->select('id'))
            ->get()
            ->groupBy('concepto_id');

        foreach ($origen->conceptos()->with('piezas')->get() as $marca) {
            $copia = Concepto::create([
                'obra_id' => $destino->obra_id,
                'catalogo_id' => $destino->id,
                // Linaje: sostiene el conteo de lo pagado aunque la marca
                // cambie de nombre en esta version.
                'concepto_origen_id' => $marca->id,
                'marca' => $marca->marca,
                'lote' => $marca->lote,
                'descripcion' => $marca->descripcion,
                'cantidad' => $marca->cantidad,
                'peso_unitario' => $marca->peso_unitario,
                'longitud' => $marca->longitud,
                'categoria_id' => $marca->categoria_id,
                'version' => $destino->version,
                'activo' => $marca->activo,
            ]);

            // Las piezas se copian con su QR y su propio linaje: el acumulado se
            // cuenta por pieza, asi que sin esto la version nueva arrancaria en
            // cero y se podria volver a pagar lo ya fabricado. Las apagadas (su
            // QR ya cambio de orden) no viajan: lo pagado bajo ellas lo
            // conserva el snapshot de la liquidacion, por modelo.
            foreach ($marca->piezas->where('activo', true) as $pieza) {
                Pieza::create([
                    'catalogo_id' => $destino->id,
                    'concepto_id' => $copia->id,
                    'qr' => $pieza->qr,
                    'qs' => $pieza->qs,
                    'pieza_origen_id' => $pieza->id,
                    'activo' => $pieza->activo,
                ]);
            }

            foreach ($preciosPorConcepto->get($marca->id, collect()) as $asignacion) {
                GrupoPrecioConcepto::create([
                    'grupo_precio_id' => $asignacion->grupo_precio_id,
                    'concepto_id' => $copia->id,
                ]);
            }
        }
    }

    /**
     * @return array{marca: string, lote: ?string, descripcion: string}
     */
    private function resumenDePieza(Concepto $pieza): array
    {
        return [
            'marca' => $pieza->marca,
            'lote' => $pieza->lote,
            'descripcion' => $pieza->descripcion,
        ];
    }

    /**
     * @return list<array{campo: string, antes: mixed, despues: mixed}>
     */
    private function cambiosEntrePiezas(Concepto $antes, Concepto $despues): array
    {
        $campos = [
            'descripcion' => 'Descripción',
            'cantidad' => 'Cantidad',
            'peso_unitario' => 'Peso unitario',
            'longitud' => 'Longitud',
            'categoria_id' => 'Categoría',
            'activo' => 'Activo',
        ];

        $cambios = [];

        foreach ($campos as $campo => $etiqueta) {
            if ((string) $antes->{$campo} === (string) $despues->{$campo}) {
                continue;
            }

            $cambios[] = [
                'campo' => $etiqueta,
                'antes' => $antes->{$campo},
                'despues' => $despues->{$campo},
            ];
        }

        return $cambios;
    }
}
