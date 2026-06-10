<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\CentroCostoStoreRequest;
use App\Http\Requests\Admin\Cotiz\CentroCostoUpdateRequest;
use App\Models\Cotiz\CentroCosto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CentroCostoController extends Controller
{
    public function index(Request $request): Response
    {
        $centrosCosto = CentroCosto::query()
            ->when($request->search, fn ($q, $s) => $q->where('cod_coste', 'like', "%{$s}%")
                ->orWhere('concepto', 'like', "%{$s}%"))
            ->orderBy('cod_coste')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/cotiz/centros-costo/index', [
            'centrosCosto' => $centrosCosto,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cotiz/centros-costo/create');
    }

    public function store(CentroCostoStoreRequest $request): RedirectResponse
    {
        CentroCosto::create($request->validated());

        return to_route('admin.cotiz.centros-costo.index');
    }

    public function edit(CentroCosto $centroCosto): Response
    {
        return Inertia::render('admin/cotiz/centros-costo/edit', [
            'centroCosto' => $centroCosto,
        ]);
    }

    public function update(CentroCostoUpdateRequest $request, CentroCosto $centroCosto): RedirectResponse
    {
        $centroCosto->update($request->validated());

        return to_route('admin.cotiz.centros-costo.index');
    }

    public function destroy(CentroCosto $centroCosto): RedirectResponse
    {
        $centroCosto->delete();

        return to_route('admin.cotiz.centros-costo.index');
    }
}
