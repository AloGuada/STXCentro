<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Models\Qal\Operador;

/**
 * Catálogo de operador. Todo el comportamiento está en la clase base:
 * alta, cambio de nombre y activar/desactivar, sin baja.
 */
class OperadorController extends CatalogoSimpleController
{
    protected function modelo(): string
    {
        return Operador::class;
    }

    protected function etiqueta(): string
    {
        return 'El operador';
    }
}
