<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\CategoriaEmpleadoStoreRequest;
use App\Http\Requests\Admin\Prod\CategoriaEmpleadoUpdateRequest;
use App\Models\Prod\CategoriaEmpleado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoriaEmpleadoController extends Controller
{
    public function index(Request $request): Response
    {
        $categorias = CategoriaEmpleado::query()
            ->when($request->search, fn ($q, $s) => $q->where('nombre', 'like', "%{$s}%"))
            ->withCount('empleados')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/categorias-empleado/index', [
            'categorias' => $categorias,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/categorias-empleado/create');
    }

    public function store(CategoriaEmpleadoStoreRequest $request): RedirectResponse
    {
        CategoriaEmpleado::create($request->validated());

        return to_route('admin.prod.categorias-empleado.index');
    }

    public function edit(CategoriaEmpleado $categoriaEmpleado): Response
    {
        return Inertia::render('admin/prod/categorias-empleado/edit', [
            'categoria' => $categoriaEmpleado->loadCount('empleados'),
        ]);
    }

    public function update(CategoriaEmpleadoUpdateRequest $request, CategoriaEmpleado $categoriaEmpleado): RedirectResponse
    {
        $categoriaEmpleado->update($request->validated());

        return to_route('admin.prod.categorias-empleado.index');
    }

    public function destroy(CategoriaEmpleado $categoriaEmpleado): RedirectResponse
    {
        if ($categoriaEmpleado->empleados()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar una categoria con empleados asignados.']);
        }

        $categoriaEmpleado->delete();

        return to_route('admin.prod.categorias-empleado.index');
    }
}
