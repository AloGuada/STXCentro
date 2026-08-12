<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plomería mínima para poder abrir las pantallas web del módulo de Calidad.
 *
 * Calidad lleva meses funcionando, pero sólo por API (`routes/api.php`, guard
 * Sanctum) y contra las tablas `cal_*`, que consume una aplicación aparte. Ésta
 * es su versión en el mono, sobre `qal_*`: la anterior se apagará y sus tablas
 * se van con ella, así que aquí no se reusa nada de allá.
 *
 * Estas vistas fijan la estructura mientras se acuerda qué hace cada una. Cada
 * pantalla corresponde a una tabla que ya existe en `qal_`. Se van reemplazando
 * por su controlador de verdad conforme se construyan, y este archivo
 * desaparece cuando no quede ninguna.
 */
class VistasController extends Controller
{
    public function obras(): Response
    {
        return Inertia::render('admin/calidad/obras/index');
    }

    public function piezas(): Response
    {
        return Inertia::render('admin/calidad/piezas/index');
    }

    public function reportes(): Response
    {
        return Inertia::render('admin/calidad/reportes/index');
    }
}
