<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\FactorStoreRequest;
use App\Http\Requests\Admin\Cotiz\FactorUpdateRequest;
use App\Models\Cotiz\CategoriaTarjeta;
use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Insumo;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FactorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/cotiz/factores/index', [
            'factores' => Factor::query()
                ->with(['insumo', 'categoriaTarjeta'])
                ->orderBy('codigo')
                ->get(),
            'insumos' => Insumo::query()->orderBy('descripcion')->get(),
            'categoriasTarjeta' => CategoriaTarjeta::query()->orderBy('orden')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cotiz/factores/create', [
            'insumos' => Insumo::query()->orderBy('descripcion')->get(),
            'categoriasTarjeta' => CategoriaTarjeta::query()->orderBy('orden')->get(),
        ]);
    }

    public function store(FactorStoreRequest $request): RedirectResponse
    {
        Factor::create($request->validated());

        return to_route('admin.cotiz.factores.index');
    }

    public function edit(Factor $factor): Response
    {
        return Inertia::render('admin/cotiz/factores/edit', [
            'factor' => $factor->load(['insumo', 'categoriaTarjeta']),
            'insumos' => Insumo::query()->orderBy('descripcion')->get(),
            'categoriasTarjeta' => CategoriaTarjeta::query()->orderBy('orden')->get(),
        ]);
    }

    public function update(FactorUpdateRequest $request, Factor $factor): RedirectResponse
    {
        $factor->update($request->validated());

        return to_route('admin.cotiz.factores.index');
    }

    public function destroy(Factor $factor): RedirectResponse
    {
        $factor->delete();

        return to_route('admin.cotiz.factores.index');
    }
}
