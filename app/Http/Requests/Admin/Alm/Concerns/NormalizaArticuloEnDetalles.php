<?php

namespace App\Http\Requests\Admin\Alm\Concerns;

use App\Services\Alm\ResolvedorArticulo;

/**
 * Mientras `producto_id` y `articulo_id` convivan, los formularios de Almacén
 * pueden mandar cualquiera de los dos y aquí se resuelve a uno solo.
 *
 * El selector nuevo manda el artículo, que es lo que queda. Pero una pantalla
 * abierta desde antes del despliegue, un JS cacheado o una llamada guardada
 * siguen mandando el producto, y tirarles la captura por un rename sería
 * cobrarles a los usuarios un cambio interno.
 *
 * La traducción es la misma que hace el resto del módulo: si ese producto nunca
 * ha pisado la bodega, se le crea su artículo ya ligado.
 *
 * **Se borra en la fase B**, junto con la columna. Es andamio, no diseño.
 */
trait NormalizaArticuloEnDetalles
{
    protected function prepareForValidation(): void
    {
        $detalles = $this->input('detalles');

        if (! is_array($detalles)) {
            return;
        }

        $resolvedor = app(ResolvedorArticulo::class);

        foreach ($detalles as $i => $renglon) {
            if (! is_array($renglon) || ! empty($renglon['articulo_id']) || empty($renglon['producto_id'])) {
                continue;
            }

            $detalles[$i]['articulo_id'] = $resolvedor->paraProducto((int) $renglon['producto_id']);
        }

        $this->merge(['detalles' => $detalles]);
    }
}
