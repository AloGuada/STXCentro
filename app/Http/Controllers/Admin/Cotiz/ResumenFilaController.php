<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Enums\Cotiz\ResumenBloque;
use App\Enums\Cotiz\ResumenTipoFormula;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\ResumenFilaStoreRequest;
use App\Http\Requests\Admin\Cotiz\ResumenFilaUpdateRequest;
use App\Models\Cotiz\ResumenFila;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResumenFilaController extends Controller
{
    public function index(Request $request): Response
    {
        $resumenFilas = ResumenFila::query()
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->orderBy('orden')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/cotiz/resumen-filas/index', [
            'resumenFilas' => $resumenFilas,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cotiz/resumen-filas/create', [
            'bloques' => ResumenBloque::options(),
            'tiposFormula' => ResumenTipoFormula::options(),
        ]);
    }

    public function store(ResumenFilaStoreRequest $request): RedirectResponse
    {
        ResumenFila::create($request->validated());

        return to_route('admin.cotiz.resumen-filas.index');
    }

    public function edit(ResumenFila $resumenFila): Response
    {
        return Inertia::render('admin/cotiz/resumen-filas/edit', [
            'resumenFila' => $resumenFila,
            'bloques' => ResumenBloque::options(),
            'tiposFormula' => ResumenTipoFormula::options(),
        ]);
    }

    public function update(ResumenFilaUpdateRequest $request, ResumenFila $resumenFila): RedirectResponse
    {
        $resumenFila->update($request->validated());

        return to_route('admin.cotiz.resumen-filas.index');
    }

    public function destroy(ResumenFila $resumenFila): RedirectResponse
    {
        $resumenFila->delete();

        return to_route('admin.cotiz.resumen-filas.index');
    }
}
