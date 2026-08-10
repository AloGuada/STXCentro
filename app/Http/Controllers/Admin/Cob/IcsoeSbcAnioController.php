<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\IcsoeSbcAnioRequest;
use App\Models\Cob\IcsoeSbcAnio;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IcsoeSbcAnioController extends Controller
{
    public function index(): Response
    {
        $this->authorize('cob.icsoe-sbc.ver');

        return Inertia::render('admin/cob/icsoe-sbc/index', [
            'anios' => IcsoeSbcAnio::query()->orderByDesc('anio')->get(),
        ]);
    }

    public function store(IcsoeSbcAnioRequest $request): RedirectResponse
    {
        IcsoeSbcAnio::create($request->validated());

        return back()->with('success', 'Año agregado al catálogo.');
    }

    public function update(IcsoeSbcAnioRequest $request, IcsoeSbcAnio $sbcAnio): RedirectResponse
    {
        $sbcAnio->update($request->validated());

        return back()->with('success', 'Año actualizado.');
    }

    public function destroy(IcsoeSbcAnio $sbcAnio): RedirectResponse
    {
        $this->authorize('cob.icsoe-sbc.editar');

        // Los meses ya calculados guardan su propio SBC, así que borrar un año
        // no altera ningún seguimiento existente.
        $sbcAnio->delete();

        return back()->with('success', 'Año eliminado del catálogo.');
    }
}
