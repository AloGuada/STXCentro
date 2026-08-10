<?php

namespace App\Exceptions\Cob;

use RuntimeException;

/**
 * Lanzada cuando se intenta calcular un ICSOE sin ningún año capturado en el
 * catálogo de SBC. Falla ruidosamente a propósito: devolver 0 dejaría pasar
 * seguimientos con mano de obra real en cero sin que nadie se entere.
 */
class CatalogoSbcVacioException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El catálogo de SBC por año está vacío: captura al menos un año antes de calcular un ICSOE.');
    }
}
