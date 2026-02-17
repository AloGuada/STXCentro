<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProveedorStoreRequest;
use App\Http\Requests\Admin\ProveedorUpdateRequest;
use App\Models\Departamento;
use App\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorController extends Controller
{
    public function index(Request $request): Response
    {
        $proveedores = Proveedor::query()
            ->with('departamento')
            ->when($request->search, fn ($q, $s) => $q->where('razon_social', 'like', "%{$s}%")
                ->orWhere('codigo', 'like', "%{$s}%")
                ->orWhere('rfc', 'like', "%{$s}%")
                ->orWhere('nombre_comercial', 'like', "%{$s}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/proveedores/index', [
            'proveedores' => $proveedores,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/proveedores/create', [
            'departamentos' => Departamento::query()->orderBy('descripcion')->get(),
        ]);
    }

    public function store(ProveedorStoreRequest $request): RedirectResponse
    {
        Proveedor::create($request->validated());

        return to_route('admin.proveedores.index');
    }

    public function edit(Proveedor $proveedor): Response
    {
        return Inertia::render('admin/proveedores/edit', [
            'proveedor' => $proveedor->load('departamento'),
            'departamentos' => Departamento::query()->orderBy('descripcion')->get(),
        ]);
    }

    public function update(ProveedorUpdateRequest $request, Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update($request->validated());

        return to_route('admin.proveedores.index');
    }

    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        $proveedor->delete();

        return to_route('admin.proveedores.index');
    }
}
