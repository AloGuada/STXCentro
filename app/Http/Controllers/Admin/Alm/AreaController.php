<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\AreaStoreRequest;
use App\Http\Requests\Admin\Alm\AreaUpdateRequest;
use App\Models\Alm\Area;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de áreas: clasifica el artículo por la parte de la operación a la
 * que pertenece.
 *
 * Una sola pantalla con el alta arriba y la lista abajo: es una lista de un
 * campo, y tres pantallas para eso sobran.
 */
class AreaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/almacen/areas/index', [
            'areas' => Area::query()->orderBy('descripcion')->get(),
        ]);
    }

    public function store(AreaStoreRequest $request): RedirectResponse
    {
        Area::create($request->validated() + ['activo' => true]);

        return back();
    }

    public function update(AreaUpdateRequest $request, Area $area): RedirectResponse
    {
        $area->update($request->validated());

        return back();
    }

    /**
     * Activa o desactiva. Es la única baja que hay: borrar el área dejaría
     * artículos apuntando a algo que ya no existe.
     */
    public function toggle(Area $area): RedirectResponse
    {
        $area->update(['activo' => ! $area->activo]);

        return back();
    }
}
