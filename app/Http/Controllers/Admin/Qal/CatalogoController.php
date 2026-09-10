<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Enums\Qal\AmbitoDefecto;
use App\Http\Controllers\Controller;
use App\Models\Qal\Defecto;
use App\Models\Qal\Equipo;
use App\Models\Qal\Laboratorio;
use App\Models\Qal\Operador;
use App\Models\Qal\Responsable;
use App\Models\Qal\Soldador;
use App\Models\Qal\SupervisorPintura;
use App\Models\Qal\TipoPieza;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los catálogos del módulo, en una sola pantalla con pestañas.
 *
 * Van juntos y no en nueve entradas de menú porque es como se administran: se
 * entra a corregir dos listas y se sale. Nueve entradas para nueve listas de dos
 * campos convertirían el menú en un directorio.
 *
 * Se cargan las nueve de una vez —son cientos de filas en total, no miles—, así
 * que cambiar de pestaña no vuelve al servidor.
 */
class CatalogoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/calidad/catalogos/index', [
            'soldadores' => Soldador::ordenNatural(Soldador::all()),
            'laboratorios' => Laboratorio::ordenNatural(Laboratorio::all()),
            'tiposPieza' => TipoPieza::ordenNatural(TipoPieza::all()),
            'equipos' => Equipo::ordenNatural(Equipo::all()),
            'operadores' => Operador::ordenNatural(Operador::all()),
            'responsables' => Responsable::ordenNatural(Responsable::all()),
            'supervisoresPintura' => SupervisorPintura::ordenNatural(SupervisorPintura::all()),
            'defectos' => Defecto::ordenNatural(Defecto::all()),
            'ambitosDefecto' => AmbitoDefecto::opciones(),
        ]);
    }
}
