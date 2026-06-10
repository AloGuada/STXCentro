<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\CentroCostoStoreRequest;
use App\Http\Requests\Admin\Cotiz\CentroCostoUpdateRequest;
use App\Models\Cotiz\CentroCosto;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CentroCostoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/cotiz/centros-costo/index', [
            'centrosCosto' => CentroCosto::query()->orderBy('cod_coste')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cotiz/centros-costo/create');
    }

    public function store(CentroCostoStoreRequest $request): RedirectResponse
    {
        CentroCosto::create($request->validated());

        return to_route('admin.cotiz.centros-costo.index');
    }

    public function edit(CentroCosto $centroCosto): Response
    {
        return Inertia::render('admin/cotiz/centros-costo/edit', [
            'centroCosto' => $centroCosto,
        ]);
    }

    public function update(CentroCostoUpdateRequest $request, CentroCosto $centroCosto): RedirectResponse
    {
        $centroCosto->update($request->validated());

        return to_route('admin.cotiz.centros-costo.index');
    }

    public function destroy(CentroCosto $centroCosto): RedirectResponse
    {
        $centroCosto->delete();

        return to_route('admin.cotiz.centros-costo.index');
    }
}
