<?php

namespace App\Services\Prod;

use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\GrupoPrecioConcepto;
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
     * Diferencias entre dos versiones, emparejadas por marca.
     *
     * @return array{
     *     agregadas: list<array{marca: string, descripcion: string}>,
     *     eliminadas: list<array{marca: string, descripcion: string}>,
     *     modificadas: list<array{marca: string, descripcion: string, cambios: list<array{campo: string, antes: mixed, despues: mixed}>}>,
     *     sin_cambios: int
     * }
     */
    public function comparar(Catalogo $base, Catalogo $contra): array
    {
        $piezasBase = $base->conceptos()->get()->keyBy('marca');
        $piezasContra = $contra->conceptos()->get()->keyBy('marca');

        $agregadas = [];
        $eliminadas = [];
        $modificadas = [];
        $sinCambios = 0;

        foreach ($piezasContra as $marca => $pieza) {
            if (! $piezasBase->has($marca)) {
                $agregadas[] = ['marca' => $pieza->marca, 'descripcion' => $pieza->descripcion];

                continue;
            }

            $cambios = $this->cambiosEntrePiezas($piezasBase->get($marca), $pieza);

            if ($cambios === []) {
                $sinCambios++;

                continue;
            }

            $modificadas[] = [
                'marca' => $pieza->marca,
                'descripcion' => $pieza->descripcion,
                'cambios' => $cambios,
            ];
        }

        foreach ($piezasBase as $marca => $pieza) {
            if (! $piezasContra->has($marca)) {
                $eliminadas[] = ['marca' => $pieza->marca, 'descripcion' => $pieza->descripcion];
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

        foreach ($origen->conceptos()->get() as $pieza) {
            $copia = Concepto::create([
                'obra_id' => $destino->obra_id,
                'catalogo_id' => $destino->id,
                'marca' => $pieza->marca,
                'descripcion' => $pieza->descripcion,
                'cantidad' => $pieza->cantidad,
                'peso_unitario' => $pieza->peso_unitario,
                'longitud' => $pieza->longitud,
                'categoria_id' => $pieza->categoria_id,
                'version' => $destino->version,
                'activo' => $pieza->activo,
            ]);

            foreach ($preciosPorConcepto->get($pieza->id, collect()) as $asignacion) {
                GrupoPrecioConcepto::create([
                    'grupo_precio_id' => $asignacion->grupo_precio_id,
                    'concepto_id' => $copia->id,
                ]);
            }
        }
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
