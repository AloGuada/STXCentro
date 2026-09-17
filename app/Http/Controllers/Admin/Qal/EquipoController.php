<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Models\Qal\Equipo;

/**
 * Catálogo de equipo. Todo el comportamiento está en la clase base:
 * alta, cambio de nombre y activar/desactivar, sin baja.
 */
class EquipoController extends CatalogoSimpleController
{
    protected function modelo(): string
    {
        return Equipo::class;
    }

    protected function etiqueta(): string
    {
        return 'El equipo';
    }
}
