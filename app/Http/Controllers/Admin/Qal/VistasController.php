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
     * Captura de inspección: el `captura.html` de la aplicación anterior.
     *
     * La pantalla ya está construida, pero todavía no recibe nada: sus
     * catálogos son de ejemplo y viven en el front. Deja de pasar por aquí
     * cuando existan las tablas de inspección y tenga su propio controlador.
     */
    public function reportes(): Response
    {
        return Inertia::render('admin/calidad/reportes/index');
    }

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
