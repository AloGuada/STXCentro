<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Models\Qal\DefectoSoldadura;

/**
 * Catálogo de defecto de soldadura. Todo el comportamiento está en la clase base:
 * alta, cambio de nombre y activar/desactivar, sin baja.
 */
class DefectoSoldaduraController extends CatalogoSimpleController
{
    protected function modelo(): string
    {
        return DefectoSoldadura::class;
    }

    protected function etiqueta(): string
    {
        return 'El defecto';
    }
}
