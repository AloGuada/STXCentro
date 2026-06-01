<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegimenFiscalRequest;
use App\Models\RegimenFiscal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RegimenFiscalController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('costos.regimenes-fiscales.ver');

        return Inertia::render('admin/regimenes-fiscales/index', [
            'regimenes' => RegimenFiscal::orderBy('clave')->get(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('costos.regimenes-fiscales.crear');

        return Inertia::render('admin/regimenes-fiscales/create');
    }

    public function store(RegimenFiscalRequest $request): RedirectResponse
    {
        Gate::authorize('costos.regimenes-fiscales.crear');

        RegimenFiscal::create($request->validated());

        return to_route('admin.regimenes-fiscales.index')->with('success', 'Régimen fiscal creado.');
    }

    public function edit(RegimenFiscal $regimenFiscal): Response
    {
        Gate::authorize('costos.regimenes-fiscales.editar');

        return Inertia::render('admin/regimenes-fiscales/edit', [
            'regimen' => $regimenFiscal,
        ]);
    }

    public function update(RegimenFiscalRequest $request, RegimenFiscal $regimenFiscal): RedirectResponse
    {
        Gate::authorize('costos.regimenes-fiscales.editar');

        $regimenFiscal->update($request->validated());

        return to_route('admin.regimenes-fiscales.index')->with('success', 'Régimen fiscal actualizado.');
    }

    public function destroy(RegimenFiscal $regimenFiscal): RedirectResponse
    {
        Gate::authorize('costos.regimenes-fiscales.eliminar');

        $regimenFiscal->delete();

        return to_route('admin.regimenes-fiscales.index')->with('success', 'Régimen fiscal eliminado.');
    }
}
