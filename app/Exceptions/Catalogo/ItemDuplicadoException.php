<?php

namespace App\Exceptions\Catalogo;

use App\Models\Item;
use RuntimeException;

/**
 * Se intentó dar de alta una cara —producto o artículo— para un insumo que ya
 * tiene esa cara. No es un error de base de datos que haya que descifrar: es
 * la regla del maestro diciendo, con código y descripción, cuál es el que ya
 * existe y hay que reutilizar.
 */
class ItemDuplicadoException extends RuntimeException
{
    public function __construct(public readonly Item $item, string $cara)
    {
        $codigo = $item->codigo !== null ? "{$item->codigo} " : '';

        parent::__construct("Ya existe {$cara} {$codigo}\"{$item->descripcion}\"; usa ese en lugar de crear otro.");
    }
}
