<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\PinturaFormulaStoreRequest;
use App\Http\Requests\Admin\Cotiz\PinturaFormulaUpdateRequest;
use App\Models\Cotiz\PinturaFormula;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PinturaFormulaController extends Controller
{
    public function index(Request $request): Response
    {
        $pinturaFormulas = PinturaFormula::query()
            ->when($request->search, fn ($q, $s) => $q->where('clave', 'like', "%{$s}%")
                ->orWhere('nombre', 'like', "%{$s}%"))
            ->orderBy('orden')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/cotiz/pintura-formulas/index', [
            'pinturaFormulas' => $pinturaFormulas,
            'filters' => $request->only('search'),
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
