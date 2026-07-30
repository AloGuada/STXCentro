<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\CategoriaStoreRequest;
use App\Http\Requests\Admin\Prod\CategoriaUpdateRequest;
use App\Models\Prod\Categoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoriaController extends Controller
{
    public function index(Request $request): Response
    {
        $categorias = Categoria::query()
            ->when($request->search, fn ($q, $s) => $q->where('nombre', 'like', "%{$s}%"))
            ->withCount('conceptos')
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/categorias/index', [
            'categorias' => $categorias,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/categorias/create');
    }

    public function store(CategoriaStoreRequest $request): RedirectResponse
    {
        Categoria::create([
            'nombre' => $request->nombre,
        ]);

        return to_route('admin.prod.categorias.index');
    }

    public function edit(Categoria $categoria): Response
    {
        return Inertia::render('admin/prod/categorias/edit', [
            'categoria' => $categoria->loadCount('conceptos'),
        ]);
    }

    public function update(CategoriaUpdateRequest $request, Categoria $categoria): RedirectResponse
    {
        $categoria->update([
            'nombre' => $request->nombre,
        ]);

        return to_route('admin.prod.categorias.index');
    }

    public function destroy(Categoria $categoria): RedirectResponse
    {
        if ($categoria->conceptos()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar una categoria con piezas asociadas.']);
        }

        $categoria->delete();

        return to_route('admin.prod.categorias.index');
    }
}
