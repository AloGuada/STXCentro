<?php

namespace App\Http\Requests\Admin\Alm;

use App\Rules\DescripcionUnicaEnCatalogo;

/**
 * Mismas reglas que el alta: el código sigue sin recibirse, porque tampoco se
 * corrige. Un artículo mal capturado se edita en su descripción; el código con
 * el que ya se etiquetaron cajas y se sellaron movimientos no se toca.
 */
class ArticuloUpdateRequest extends ArticuloStoreRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.articulos.editar') ?? false;
    }

    protected function descripcionUnica(): DescripcionUnicaEnCatalogo
    {
        return DescripcionUnicaEnCatalogo::paraEdicion($this->route('articulo')?->item_id);
    }
}
