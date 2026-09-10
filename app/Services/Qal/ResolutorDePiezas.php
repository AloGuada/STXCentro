<?php

namespace App\Services\Qal;

use App\Models\Prod\Pieza;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Encuentra la pieza física de Producción por lo que se leyó de su etiqueta.
 *
 * Lo normal es el QR, que es único dentro de un catálogo. Si no aparece se
 * prueba el QS: es el número con el que la gente de planta nombra la pieza y el
 * que se teclea cuando la etiqueta no se deja leer, pero puede repetirse entre
 * lotes, así que puede devolver más de una.
 *
 * Sólo se busca en el catálogo vigente. Tras versionar, el mismo QR existe en
 * varias versiones, y la congelada no es la que está en la nave.
 */
class ResolutorDePiezas
{
    /**
     * Las candidatas: ninguna, una, o varias si el código es ambiguo. Sin obra
     * se busca en todas, que es lo que pasa al escanear antes de elegirla.
     *
     * @return Collection<int, Pieza>
     */
    public function candidatas(string $codigo, ?int $obraId = null): Collection
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return new Collection;
        }

        $porQr = $this->consulta($obraId)->where('qr', $codigo)->get();

        return $porQr->isNotEmpty() ? $porQr : $this->consulta($obraId)->where('qs', $codigo)->get();
    }

    /**
     * @return Builder<Pieza>
     */
    private function consulta(?int $obraId): Builder
    {
        return Pieza::query()
            ->deCatalogoVigente()
            ->when($obraId, fn (Builder $consulta) => $consulta->whereHas(
                'catalogo',
                fn (Builder $catalogo) => $catalogo->where('obra_id', $obraId),
            ))
            ->with(['marca', 'catalogo:id,obra_id,version']);
    }
}
