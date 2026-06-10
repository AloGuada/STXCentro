<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\InsumoStoreRequest;
use App\Http\Requests\Admin\Cotiz\InsumoUpdateRequest;
use App\Models\Cotiz\CategoriaTarjeta;
use App\Models\Cotiz\CentroCosto;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\Unidad;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InsumoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/cotiz/insumos/index', [
            'insumos' => Insumo::query()
                ->with(['unidad', 'centroCosto', 'categoriaTarjeta'])
                ->orderBy('descripcion')
                ->get(),
            'unidades' => Unidad::query()->orderBy('descripcion')->get(),
            'centrosCosto' => CentroCosto::query()->orderBy('concepto')->get(),
            'categoriasTarjeta' => CategoriaTarjeta::query()->orderBy('orden')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cotiz/insumos/create', [
            'unidades' => Unidad::query()->orderBy('descripcion')->get(),
            'centrosCosto' => CentroCosto::query()->orderBy('concepto')->get(),
            'categoriasTarjeta' => CategoriaTarjeta::query()->orderBy('orden')->get(),
        ]);
    }

    public function store(InsumoStoreRequest $request): RedirectResponse
    {
        Insumo::create($request->validated());

        return to_route('admin.cotiz.insumos.index');
    }

    public function edit(Insumo $insumo): Response
    {
        return Inertia::render('admin/cotiz/insumos/edit', [
            'insumo' => $insumo->load(['unidad', 'centroCosto', 'categoriaTarjeta']),
            'unidades' => Unidad::query()->orderBy('descripcion')->get(),
            'centrosCosto' => CentroCosto::query()->orderBy('concepto')->get(),
            'categoriasTarjeta' => CategoriaTarjeta::query()->orderBy('orden')->get(),
        ]);
    }

    public function update(InsumoUpdateRequest $request, Insumo $insumo): RedirectResponse
    {
        $insumo->update($request->validated());

        return to_route('admin.cotiz.insumos.index');
    }

    public function destroy(Insumo $insumo): RedirectResponse
    {
        $insumo->delete();

        return to_route('admin.cotiz.insumos.index');
    }
}
