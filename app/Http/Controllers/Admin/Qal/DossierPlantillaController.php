<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\ArbolDeSeccionesRequest;
use App\Http\Requests\Admin\Qal\DossierPlantillaRequest;
use App\Models\Qal\DossierPlantilla;
use App\Services\Qal\Dosier\ArbolDeSecciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * El catálogo de plantillas del dosier.
 *
 * Una plantilla no se borra, se desactiva: los dosieres que nacieron de ella
 * guardan su nombre, y una desactivada sólo deja de ofrecerse al crear uno
 * nuevo. El árbol se guarda entero de una vez —es lo que hace un editor de
 * árbol: acomodar varias cosas y guardar—, no sección por sección.
 */
class DossierPlantillaController extends Controller
{
    public function __construct(private ArbolDeSecciones $arboles) {}

    public function store(DossierPlantillaRequest $request): RedirectResponse
    {
        $plantilla = DB::transaction(function () use ($request): DossierPlantilla {
            $plantilla = DossierPlantilla::create($request->safe()->only(['nombre', 'descripcion']) + ['activo' => true]);

            if ($request->filled('desde')) {
                $this->arboles->guardar($plantilla->secciones(), DossierPlantilla::query()->findOrFail($request->integer('desde'))->arbol());
            }

            return $plantilla;
        });

        return to_route('admin.qal.dosier.index', ['tab' => 'catalogo', 'plantilla' => $plantilla->id])
            ->with('success', "Plantilla «{$plantilla->nombre}» creada.");
    }

    public function update(DossierPlantillaRequest $request, DossierPlantilla $plantilla): RedirectResponse
    {
        $plantilla->update($request->safe()->only(['nombre', 'descripcion']));

        return back()->with('success', 'Plantilla actualizada.');
    }

    public function toggle(DossierPlantilla $plantilla): RedirectResponse
    {
        $plantilla->update(['activo' => ! $plantilla->activo]);

        return back()->with('success', $plantilla->activo ? 'Plantilla reactivada.' : 'Plantilla desactivada: ya no se ofrece al crear un dosier.');
    }

    public function arbol(ArbolDeSeccionesRequest $request, DossierPlantilla $plantilla): RedirectResponse
    {
        $this->arboles->guardar($plantilla->secciones(), $request->input('arbol'));

        return back()->with('success', 'Secciones guardadas.');
    }
}
