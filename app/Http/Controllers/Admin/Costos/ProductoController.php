<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\ProductoStoreRequest;
use App\Http\Requests\Admin\Costos\ProductoUpdateRequest;
use App\Models\Costos\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de productos de Costos (código + descripción + unidad) con histórico
 * de precios. Administrado por Compras y Almacén.
 */
class ProductoController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('costos.productos.ver');

        $productos = Producto::query()
            ->withCount('precios')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('descripcion', 'like', "%{$search}%")
                        ->orWhere('codigo', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->orderBy('descripcion')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/costos/productos/index', [
            'productos' => $productos,
            'filters' => $request->only('search', 'activo'),
        ]);
    }

    /**
     * Búsqueda para autocompletar en el alta de requisición (y OC). Devuelve
     * los productos activos que coinciden por código o descripción.
     */
    public function buscar(Request $request): JsonResponse
    {
        Gate::authorize('costos.productos.ver');

        $term = (string) $request->input('q', '');

        $productos = Producto::query()
            ->where('activo', true)
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($w) use ($term) {
                    $w->where('descripcion', 'like', "%{$term}%")
                        ->orWhere('codigo', 'like', "%{$term}%");
                });
            })
            ->orderBy('descripcion')
            ->limit(20)
            ->get(['id', 'codigo', 'descripcion', 'unidad']);

        return response()->json($productos);
    }

    public function create(): Response
    {
        Gate::authorize('costos.productos.crear');

        return Inertia::render('admin/costos/productos/create');
    }

    public function store(ProductoStoreRequest $request): RedirectResponse
    {
        $producto = Producto::create([
            ...$request->validated(),
            'creado_por' => $request->user()->id,
        ]);

        return to_route('admin.costos.productos.edit', $producto)->with('success', 'Producto creado.');
    }

    public function edit(Producto $producto): Response
    {
        Gate::authorize('costos.productos.ver');

        $producto->load(['precios.proveedor:id,razon_social', 'creador:id,name']);

        return Inertia::render('admin/costos/productos/edit', [
            'producto' => $producto,
        ]);
    }

    public function update(ProductoUpdateRequest $request, Producto $producto): RedirectResponse
    {
        $producto->update($request->validated());

        return back()->with('success', 'Producto actualizado.');
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        Gate::authorize('costos.productos.eliminar');

        $producto->delete();

        return to_route('admin.costos.productos.index')->with('success', 'Producto eliminado.');
    }
}
