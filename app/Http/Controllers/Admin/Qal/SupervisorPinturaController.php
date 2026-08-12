<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Models\Qal\SupervisorPintura;

/**
 * Catálogo de supervisor de pintura. Todo el comportamiento está en la clase base:
 * alta, cambio de nombre y activar/desactivar, sin baja.
 */
class SupervisorPinturaController extends CatalogoSimpleController
{
    protected function modelo(): string
    {
        return SupervisorPintura::class;
    }

    protected function etiqueta(): string
    {
        return 'El supervisor';
    }
}
