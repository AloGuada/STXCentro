<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\TipoPagoExtraStoreRequest;
use App\Http\Requests\Admin\Prod\TipoPagoExtraUpdateRequest;
use App\Models\Prod\TipoPagoExtra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TipoPagoExtraController extends Controller
{
    public function index(Request $request): Response
    {
        $tipos = TipoPagoExtra::query()
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->orderBy('orden')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/tipos-pago-extra/index', [
            'tipos' => $tipos,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/tipos-pago-extra/create');
    }

    public function store(TipoPagoExtraStoreRequest $request): RedirectResponse
    {
        TipoPagoExtra::create([
            'descripcion' => $request->descripcion,
            'orden' => $request->orden,
            'desgloce' => $request->desgloce,
        ]);

        return to_route('admin.prod.tipos-pago-extra.index');
    }

    public function edit(TipoPagoExtra $tipoPagoExtra): Response
    {
        return Inertia::render('admin/prod/tipos-pago-extra/edit', [
            'tipo' => $tipoPagoExtra,
        ]);
    }

    public function update(TipoPagoExtraUpdateRequest $request, TipoPagoExtra $tipoPagoExtra): RedirectResponse
    {
        $tipoPagoExtra->update([
            'descripcion' => $request->descripcion,
            'orden' => $request->orden,
            'desgloce' => $request->desgloce,
        ]);

        return to_route('admin.prod.tipos-pago-extra.index');
    }

    public function destroy(TipoPagoExtra $tipoPagoExtra): RedirectResponse
    {
        $tipoPagoExtra->delete();

        return to_route('admin.prod.tipos-pago-extra.index');
    }
}
