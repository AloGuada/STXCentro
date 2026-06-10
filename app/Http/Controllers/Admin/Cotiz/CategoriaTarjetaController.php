<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\CategoriaTarjetaStoreRequest;
use App\Http\Requests\Admin\Cotiz\CategoriaTarjetaUpdateRequest;
use App\Models\Cotiz\CategoriaTarjeta;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoriaTarjetaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/cotiz/categorias-tarjeta/index', [
            'categoriasTarjeta' => CategoriaTarjeta::query()->orderBy('orden')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cotiz/categorias-tarjeta/create');
    }

    public function store(CategoriaTarjetaStoreRequest $request): RedirectResponse
    {
        CategoriaTarjeta::create($request->validated());

        return to_route('admin.cotiz.categorias-tarjeta.index');
    }

    public function edit(CategoriaTarjeta $categoriaTarjeta): Response
    {
        return Inertia::render('admin/cotiz/categorias-tarjeta/edit', [
            'categoriaTarjeta' => $categoriaTarjeta,
        ]);
    }

    public function update(CategoriaTarjetaUpdateRequest $request, CategoriaTarjeta $categoriaTarjeta): RedirectResponse
    {
        $categoriaTarjeta->update($request->validated());

        return to_route('admin.cotiz.categorias-tarjeta.index');
    }

    public function destroy(CategoriaTarjeta $categoriaTarjeta): RedirectResponse
    {
        $categoriaTarjeta->delete();

        return to_route('admin.cotiz.categorias-tarjeta.index');
    }
}
