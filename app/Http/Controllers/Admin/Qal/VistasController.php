<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Services\Qal\AvanceDeAccesorios;
use Illuminate\Http\Request;
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
 * Estas vistas fijan la estructura mientras se acuerda qué hace cada una. Se
 * van reemplazando por su controlador de verdad conforme se construyan, y este
 * archivo desaparece cuando no quede ninguna.
 *
 * Obras y Piezas y Planos vivían aquí y se retiraron: Calidad no administra su
 * propio padrón, va a leer el catálogo de Producción.
 */
class VistasController extends Controller
{
    /**
     * Tablero de Calidad: el `Dashboard_Calidad_Steelex.html` de la aplicación
     * anterior, reestructurado en una sola página.
     *
     * Casi todo sigue saliendo de un módulo del front marcado como falso. La
     * excepción es la pestaña Accesorios, que ya lee los lotes de verdad: se
     * calcula sólo cuando se abre (`?tab=accesorios`) y sólo para quien puede
     * ver los lotes. La URL la recuerda para que corregir un sublote regrese a
     * ella.
     */
    public function dashboard(Request $request, AvanceDeAccesorios $accesorios): Response
    {
        $enAccesorios = $request->string('tab')->value() === 'accesorios';

        return Inertia::render('admin/calidad/dashboard/index', [
            'tab' => $enAccesorios ? 'accesorios' : null,
            'accesorios' => $enAccesorios && $request->user()?->can('qal.accesorios.ver')
                ? $accesorios->tablero($request->integer('obra') ?: null)
                : null,
        ]);
    }
}
