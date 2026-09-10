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
     * Registros: el `Registros_Steelex.html` de la aplicación anterior.
     *
     * Es la tabla de auditoría del módulo —lo capturado en crudo, sin resumir—,
     * no un reporte. Comparte pantalla con los lotes de accesorios porque son
     * dos conjuntos de la misma base, aunque no compartan columnas.
     *
     * Tampoco recibe nada todavía: sus filas salen de un módulo del front
     * marcado como falso, porque `qal_inspecciones` y las tablas de accesorios
     * no existen.
     */
    public function registros(): Response
    {
        return Inertia::render('admin/calidad/registros/index');
    }

    /**
     * Avance de producción: el `Produccion_Steelex.html` de la aplicación
     * anterior.
     *
     * El control semanal de lo que producción programó contra lo que calidad
     * inspeccionó. Se escribe la lista de marcas de la semana y el resto se
     * deduce; la unidad es la pieza, no la cantidad.
     *
     * Tampoco recibe nada todavía: le faltan `qal_inspecciones` y
     * `qal_programaciones`, así que el plan no se puede guardar.
     */
    public function avance(): Response
    {
        return Inertia::render('admin/calidad/avance/index');
    }

    /**
     * Tablero de Calidad: el `Dashboard_Calidad_Steelex.html` de la aplicación
     * anterior, reestructurado en una sola página.
     *
     * Tampoco recibe nada todavía. Sus números salen de un módulo del front
     * marcado como falso porque las tablas de inspección —de donde tendría que
     * calcularlos— no existen; se conecta cuando existan.
     */
    public function dashboard(): Response
    {
        return Inertia::render('admin/calidad/dashboard/index');
    }
}
