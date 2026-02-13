<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\AprobacionDepartamentoStoreRequest;
use App\Http\Requests\Admin\Costos\AprobacionDepartamentoUpdateRequest;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Departamento;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AprobacionDepartamentoController extends Controller
{
    public function index(Request $request): Response
    {
        $aprobaciones = AprobacionDepartamento::query()
            ->with(['departamento', 'aprobador'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre_nivel', 'like', "%{$search}%")
                        ->orWhereHas('departamento', fn ($d) => $d->where('descripcion', 'like', "%{$search}%"))
                        ->orWhereHas('aprobador', fn ($a) => $a->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('departamento_id')
            ->orderBy('nivel')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/costos/aprobaciones-departamento/index', [
            'aprobaciones' => $aprobaciones,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/costos/aprobaciones-departamento/create', [
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'usuarios' => Usuario::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(AprobacionDepartamentoStoreRequest $request): RedirectResponse
    {
        AprobacionDepartamento::create($request->validated());

        return to_route('admin.costos.aprobaciones-departamento.index');
    }

    public function edit(AprobacionDepartamento $aprobacionDepartamento): Response
    {
        return Inertia::render('admin/costos/aprobaciones-departamento/edit', [
            'aprobacion' => $aprobacionDepartamento->load(['departamento', 'aprobador']),
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'usuarios' => Usuario::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(AprobacionDepartamentoUpdateRequest $request, AprobacionDepartamento $aprobacionDepartamento): RedirectResponse
    {
        $aprobacionDepartamento->update($request->validated());

        return to_route('admin.costos.aprobaciones-departamento.index');
    }

    public function destroy(AprobacionDepartamento $aprobacionDepartamento): RedirectResponse
    {
        $aprobacionDepartamento->delete();

        return to_route('admin.costos.aprobaciones-departamento.index');
    }
}
