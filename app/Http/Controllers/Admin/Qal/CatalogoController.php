<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Enums\Qal\AmbitoDefecto;
use App\Enums\Qal\OrigenFirmante;
use App\Http\Controllers\Controller;
use App\Models\Qal\Defecto;
use App\Models\Qal\Equipo;
use App\Models\Qal\Firmante;
use App\Models\Qal\Laboratorio;
use App\Models\Qal\Operador;
use App\Models\Qal\Responsable;
use App\Models\Qal\Soldador;
use App\Models\Qal\SupervisorPintura;
use App\Models\Qal\TipoPieza;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los catálogos del módulo, en una sola pantalla con pestañas.
 *
 * Van juntos y no en nueve entradas de menú porque es como se administran: se
 * entra a corregir dos listas y se sale. Nueve entradas para nueve listas de dos
 * campos convertirían el menú en un directorio.
 *
 * Se cargan todas de una vez —son cientos de filas en total, no miles—, así
 * que cambiar de pestaña no vuelve al servidor. La lista de usuarios para
 * elegir firmante sólo viaja a quien puede cambiar las firmas.
 */
class CatalogoController extends Controller
{
    public function index(Request $request): Response
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
            'firmantes' => $this->firmantes(),
            'usuariosFirmantes' => $request->user()->can('qal.firmantes.editar') ? $this->usuariosFirmantes() : [],
            'origenesFirmante' => OrigenFirmante::opciones(),
        ]);
    }

    /**
     * @return list<array{id: int, orden: int, etiqueta: string, cargo: string, origen: string, usuario_id: string|null, usuario: string|null, tiene_rubrica: bool}>
     */
    private function firmantes(): array
    {
        return Firmante::query()
            ->with('usuario:id,name,firma_path')
            ->orderBy('orden')
            ->get()
            ->map(fn (Firmante $firmante): array => [
                'id' => $firmante->id,
                'orden' => $firmante->orden,
                'etiqueta' => $firmante->etiqueta,
                'cargo' => $firmante->cargo,
                'origen' => $firmante->origen->value,
                'usuario_id' => $firmante->usuario_id,
                'usuario' => $firmante->usuario?->name,
                'tiene_rubrica' => (bool) $firmante->usuario?->firma_path,
            ])
            ->all();
    }

    /**
     * @return list<array{id: string, nombre: string, tiene_rubrica: bool}>
     */
    private function usuariosFirmantes(): array
    {
        return Usuario::query()
            ->activos()
            ->orderBy('name')
            ->get(['id', 'name', 'firma_path'])
            ->map(fn (Usuario $usuario): array => [
                'id' => $usuario->id,
                'nombre' => $usuario->name,
                'tiene_rubrica' => (bool) $usuario->firma_path,
            ])
            ->all();
    }
}
