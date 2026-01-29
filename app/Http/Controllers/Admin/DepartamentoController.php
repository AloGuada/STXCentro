<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepartamentoStoreRequest;
use App\Http\Requests\Admin\DepartamentoUpdateRequest;
use App\Models\Departamento;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartamentoController extends Controller
{
    public function index(Request $request): Response
    {
        $departamentos = Departamento::query()
            ->with('managerUsuario')
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%")
                ->orWhere('manager', 'like', "%{$s}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/departamentos/index', [
            'departamentos' => $departamentos,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/departamentos/create', [
            'usuarios' => Usuario::select('id', 'name', 'email')->orderBy('name')->get(),
        ]);
    }

    public function store(DepartamentoStoreRequest $request): RedirectResponse
    {
        Departamento::create($request->validated());

        return to_route('admin.departamentos.index');
    }

    public function edit(Departamento $departamento): Response
    {
        return Inertia::render('admin/departamentos/edit', [
            'departamento' => $departamento->load('managerUsuario'),
            'usuarios' => Usuario::select('id', 'name', 'email')->orderBy('name')->get(),
        ]);
    }

    public function update(DepartamentoUpdateRequest $request, Departamento $departamento): RedirectResponse
    {
        $departamento->update($request->validated());

        return to_route('admin.departamentos.index');
    }

    public function destroy(Departamento $departamento): RedirectResponse
    {
        $departamento->delete();

        return to_route('admin.departamentos.index');
    }
}
