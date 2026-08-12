<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Models\Qal\DefectoPintura;

/**
 * Catálogo de defecto de pintura. Todo el comportamiento está en la clase base:
 * alta, cambio de nombre y activar/desactivar, sin baja.
 */
class DefectoPinturaController extends CatalogoSimpleController
{
    protected function modelo(): string
    {
        return DefectoPintura::class;
    }

    protected function etiqueta(): string
    {
        return 'El defecto';
    }
}
