<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\TableroRequest;
use App\Services\Qal\AvanceDeAccesorios;
use App\Services\Qal\FilasDelTablero;
use App\Services\Qal\TableroCalidad;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tablero de Calidad: el `Dashboard_Calidad_Steelex.html` de la aplicación
 * anterior, reestructurado en una sola página y calculado de `qal_inspecciones`.
 *
 * Los filtros viajan en la URL y acotan todo, Accesorios incluida: la obra de
 * la barra es la misma que la de los lotes. Accesorios sólo se calcula con su
 * pestaña abierta (`?tab=accesorios`) y para quien puede ver los lotes; la URL
 * la recuerda para que corregir un sublote regrese a ella.
 */
class DashboardController extends Controller
{
    public function index(
        TableroRequest $request,
        TableroCalidad $tablero,
        FilasDelTablero $filas,
        AvanceDeAccesorios $accesorios,
    ): Response {
        $filtros = $request->filtros();
        $enAccesorios = $request->validated('tab') === 'accesorios';

        return Inertia::render('admin/calidad/dashboard/index', [
            'tab' => $enAccesorios ? 'accesorios' : null,
            'filtros' => $filtros,
            'opciones' => fn (): array => $filas->opciones(),
            'tablero' => fn (): array => $tablero->calcular($filtros),
            'accesorios' => fn (): ?array => $enAccesorios && $request->user()?->can('qal.accesorios.ver')
                ? $accesorios->tablero($filtros['obra'] !== null ? (int) $filtros['obra'] : null)
                : null,
        ]);
    }
}
