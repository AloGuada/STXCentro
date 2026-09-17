<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Models\Qal\Responsable;

/**
 * Catálogo de responsable. Todo el comportamiento está en la clase base:
 * alta, cambio de nombre y activar/desactivar, sin baja.
 */
class ResponsableController extends CatalogoSimpleController
{
    protected function modelo(): string
    {
        return Responsable::class;
    }

    protected function etiqueta(): string
    {
        return 'El responsable';
    }
}
