<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Enums\Cotiz\GrupoFlete;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\FleteViaticoCatalogoStoreRequest;
use App\Http\Requests\Admin\Cotiz\FleteViaticoCatalogoUpdateRequest;
use App\Models\Cotiz\FleteViaticoCatalogo;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FleteViaticoCatalogoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/cotiz/fletes-viaticos/index', [
            'fletesViaticos' => FleteViaticoCatalogo::query()
                ->orderBy('grupo')
                ->orderBy('orden')
                ->get(),
            'grupos' => GrupoFlete::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cotiz/fletes-viaticos/create', [
            'grupos' => GrupoFlete::options(),
        ]);
    }

    public function store(FleteViaticoCatalogoStoreRequest $request): RedirectResponse
    {
        FleteViaticoCatalogo::create($request->validated());

        return to_route('admin.cotiz.fletes-viaticos.index');
    }

    public function edit(FleteViaticoCatalogo $fleteViatico): Response
    {
        return Inertia::render('admin/cotiz/fletes-viaticos/edit', [
            'fleteViatico' => $fleteViatico,
            'grupos' => GrupoFlete::options(),
        ]);
    }

    public function update(FleteViaticoCatalogoUpdateRequest $request, FleteViaticoCatalogo $fleteViatico): RedirectResponse
    {
        $fleteViatico->update($request->validated());

        return to_route('admin.cotiz.fletes-viaticos.index');
    }

    public function destroy(FleteViaticoCatalogo $fleteViatico): RedirectResponse
    {
        $fleteViatico->delete();

        return to_route('admin.cotiz.fletes-viaticos.index');
    }
}
