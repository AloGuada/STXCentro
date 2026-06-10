<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Enums\Cotiz\TipoCorte;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\KilosRealesCategoriaStoreRequest;
use App\Http\Requests\Admin\Cotiz\KilosRealesCategoriaUpdateRequest;
use App\Models\Cotiz\KilosRealesCategoria;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class KilosRealesCategoriaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/cotiz/kilos-reales-categorias/index', [
            'kilosRealesCategorias' => KilosRealesCategoria::query()->orderBy('orden')->get(),
            'tiposCorte' => TipoCorte::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cotiz/kilos-reales-categorias/create', [
            'tiposCorte' => TipoCorte::options(),
        ]);
    }

    public function store(KilosRealesCategoriaStoreRequest $request): RedirectResponse
    {
        KilosRealesCategoria::create($request->validated());

        return to_route('admin.cotiz.kilos-reales-categorias.index');
    }

    public function edit(KilosRealesCategoria $kilosRealesCategoria): Response
    {
        return Inertia::render('admin/cotiz/kilos-reales-categorias/edit', [
            'kilosRealesCategoria' => $kilosRealesCategoria,
            'tiposCorte' => TipoCorte::options(),
        ]);
    }

    public function update(KilosRealesCategoriaUpdateRequest $request, KilosRealesCategoria $kilosRealesCategoria): RedirectResponse
    {
        $kilosRealesCategoria->update($request->validated());

        return to_route('admin.cotiz.kilos-reales-categorias.index');
    }

    public function destroy(KilosRealesCategoria $kilosRealesCategoria): RedirectResponse
    {
        $kilosRealesCategoria->delete();

        return to_route('admin.cotiz.kilos-reales-categorias.index');
    }
}
