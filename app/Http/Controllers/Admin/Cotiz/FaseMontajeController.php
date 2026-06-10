<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\FaseMontajeStoreRequest;
use App\Http\Requests\Admin\Cotiz\FaseMontajeUpdateRequest;
use App\Models\Cotiz\CentroCosto;
use App\Models\Cotiz\FaseMontaje;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FaseMontajeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/cotiz/fases-montaje/index', [
            'fasesMontaje' => FaseMontaje::query()
                ->with(['centroCosto'])
                ->orderBy('orden')
                ->get(),
            'centrosCosto' => CentroCosto::query()->orderBy('concepto')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cotiz/fases-montaje/create', [
            'centrosCosto' => CentroCosto::query()->orderBy('concepto')->get(),
        ]);
    }

    public function store(FaseMontajeStoreRequest $request): RedirectResponse
    {
        FaseMontaje::create($request->validated());

        return to_route('admin.cotiz.fases-montaje.index');
    }

    public function edit(FaseMontaje $faseMontaje): Response
    {
        return Inertia::render('admin/cotiz/fases-montaje/edit', [
            'faseMontaje' => $faseMontaje->load(['centroCosto']),
            'centrosCosto' => CentroCosto::query()->orderBy('concepto')->get(),
        ]);
    }

    public function update(FaseMontajeUpdateRequest $request, FaseMontaje $faseMontaje): RedirectResponse
    {
        $faseMontaje->update($request->validated());

        return to_route('admin.cotiz.fases-montaje.index');
    }

    public function destroy(FaseMontaje $faseMontaje): RedirectResponse
    {
        $faseMontaje->delete();

        return to_route('admin.cotiz.fases-montaje.index');
    }
}
