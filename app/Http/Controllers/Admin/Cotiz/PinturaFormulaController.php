<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\PinturaFormulaStoreRequest;
use App\Http\Requests\Admin\Cotiz\PinturaFormulaUpdateRequest;
use App\Models\Cotiz\PinturaFormula;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PinturaFormulaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/cotiz/pintura-formulas/index', [
            'pinturaFormulas' => PinturaFormula::query()->orderBy('orden')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cotiz/pintura-formulas/create');
    }

    public function store(PinturaFormulaStoreRequest $request): RedirectResponse
    {
        PinturaFormula::create($request->validated());

        return to_route('admin.cotiz.pintura-formulas.index');
    }

    public function edit(PinturaFormula $pinturaFormula): Response
    {
        return Inertia::render('admin/cotiz/pintura-formulas/edit', [
            'pinturaFormula' => $pinturaFormula,
        ]);
    }

    public function update(PinturaFormulaUpdateRequest $request, PinturaFormula $pinturaFormula): RedirectResponse
    {
        $pinturaFormula->update($request->validated());

        return to_route('admin.cotiz.pintura-formulas.index');
    }

    public function destroy(PinturaFormula $pinturaFormula): RedirectResponse
    {
        $pinturaFormula->delete();

        return to_route('admin.cotiz.pintura-formulas.index');
    }
}
