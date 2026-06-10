<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\CuadrillaStoreRequest;
use App\Http\Requests\Admin\Cotiz\CuadrillaUpdateRequest;
use App\Models\Cotiz\CentroCosto;
use App\Models\Cotiz\Cuadrilla;
use App\Models\Cotiz\Insumo;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CuadrillaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/cotiz/cuadrillas/index', [
            'cuadrillas' => Cuadrilla::query()
                ->with(['centroCosto', 'insumo'])
                ->orderBy('codigo')
                ->get(),
            'centrosCosto' => CentroCosto::query()->orderBy('concepto')->get(),
            'insumos' => Insumo::query()->orderBy('descripcion')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cotiz/cuadrillas/create', [
            'centrosCosto' => CentroCosto::query()->orderBy('concepto')->get(),
            'insumos' => Insumo::query()->orderBy('descripcion')->get(),
        ]);
    }

    public function store(CuadrillaStoreRequest $request): RedirectResponse
    {
        Cuadrilla::create($request->validated());

        return to_route('admin.cotiz.cuadrillas.index');
    }

    public function edit(Cuadrilla $cuadrilla): Response
    {
        return Inertia::render('admin/cotiz/cuadrillas/edit', [
            'cuadrilla' => $cuadrilla->load(['centroCosto', 'insumo']),
            'centrosCosto' => CentroCosto::query()->orderBy('concepto')->get(),
            'insumos' => Insumo::query()->orderBy('descripcion')->get(),
        ]);
    }

    public function update(CuadrillaUpdateRequest $request, Cuadrilla $cuadrilla): RedirectResponse
    {
        $cuadrilla->update($request->validated());

        return to_route('admin.cotiz.cuadrillas.index');
    }

    public function destroy(Cuadrilla $cuadrilla): RedirectResponse
    {
        $cuadrilla->delete();

        return to_route('admin.cotiz.cuadrillas.index');
    }
}
