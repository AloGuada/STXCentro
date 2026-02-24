<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\TipoRetencionStoreRequest;
use App\Http\Requests\Admin\Cob\TipoRetencionUpdateRequest;
use App\Models\Cob\TipoRetencion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TipoRetencionController extends Controller
{
    public function index(Request $request): Response
    {
        $tiposRetenciones = TipoRetencion::query()
            ->withCount('retenciones')
            ->when($request->search, fn ($q, $s) => $q->where('nombre', 'like', "%{$s}%"))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/cob/tipos-retenciones/index', [
            'tiposRetenciones' => $tiposRetenciones,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cob/tipos-retenciones/create');
    }

    public function store(TipoRetencionStoreRequest $request): RedirectResponse
    {
        TipoRetencion::create($request->validated());

        return to_route('admin.cob.tipos-retenciones.index');
    }

    public function edit(TipoRetencion $tipoRetencion): Response
    {
        return Inertia::render('admin/cob/tipos-retenciones/edit', [
            'tipoRetencion' => $tipoRetencion,
        ]);
    }

    public function update(TipoRetencionUpdateRequest $request, TipoRetencion $tipoRetencion): RedirectResponse
    {
        $tipoRetencion->update($request->validated());

        return to_route('admin.cob.tipos-retenciones.index');
    }

    public function destroy(TipoRetencion $tipoRetencion): RedirectResponse
    {
        if ($tipoRetencion->retenciones()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un tipo de retencion en uso.']);
        }

        $tipoRetencion->delete();

        return to_route('admin.cob.tipos-retenciones.index');
    }
}
