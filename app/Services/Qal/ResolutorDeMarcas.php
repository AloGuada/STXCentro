<?php

namespace App\Services\Qal;

use App\Models\Concepto;
use App\Models\Qal\Modelo;
use App\Models\Qal\ModeloMarca;

/**
 * Amarra una marca escrita en otro lado —la del modelo 3D, la del informe de
 * PND— a la marca del catálogo vigente de Producción.
 *
 * El IFC y el catálogo se cargan por separado y no siempre en ese orden, así
 * que el amarre se puede volver a pedir (al versionar el catálogo, o cuando
 * Producción da de alta marcas que faltaban). Sólo se amarra una marca que es
 * única en el catálogo: la misma marca en dos lotes de fabricación no se
 * adivina.
 */
class ResolutorDeMarcas
{
    /**
     * @return array<string, int> marca en mayúsculas → id de la marca de Producción
     */
    public function deLaObra(int $obraId): array
    {
        return Concepto::query()
            ->deCatalogoVigente()
            ->where('obra_id', $obraId)
            ->get(['id', 'marca'])
            ->groupBy(fn (Concepto $concepto): string => $this->normalizar($concepto->marca))
            ->filter(fn ($iguales): bool => $iguales->count() === 1)
            ->map(fn ($iguales): int => $iguales->first()->id)
            ->all();
    }

    public function conceptoDe(string $marca, array $conceptos): ?int
    {
        return $conceptos[$this->normalizar($marca)] ?? null;
    }

    /**
     * Vuelve a amarrar todas las marcas del modelo. Una que ya no existe en el
     * catálogo vigente se suelta.
     *
     * @return int cuántas quedaron amarradas
     */
    public function resolver(Modelo $modelo): int
    {
        $conceptos = $this->deLaObra($modelo->obra_id);
        $amarradas = 0;

        $modelo->marcas()->each(function (ModeloMarca $marca) use ($conceptos, &$amarradas): void {
            $concepto = $this->conceptoDe($marca->marca, $conceptos);

            if ($marca->concepto_id !== $concepto) {
                $marca->update(['concepto_id' => $concepto]);
            }

            $amarradas += $concepto === null ? 0 : 1;
        });

        return $amarradas;
    }

    private function normalizar(?string $marca): string
    {
        return mb_strtoupper(trim((string) $marca));
    }
}
