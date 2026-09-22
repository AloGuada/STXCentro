<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plomería mínima para poder abrir las maquetas del módulo.
 *
 * Esta pantalla todavía no tiene backend: se dibuja con los datos de ejemplo
 * de `resources/js/lib/alm/demo.ts` para revisar diseño y flujo. Cuando gane su
 * controlador de verdad, este archivo desaparece.
 */
class VistasController extends Controller
{
    public function etiquetas(): Response
    {
        return Inertia::render('admin/almacen/etiquetas/index');
    }
}
