<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\PagoExtraStoreRequest;
use App\Models\Prod\Corte;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\TipoPagoExtra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PagoExtraController extends Controller
{
    public function index(Request $request): Response
    {
        $pagosExtra = PagoExtra::query()
            ->with(['tipo', 'corte', 'grupoTrabajo'])
            ->when($request->corte_id, fn ($q, $id) => $q->where('corte_id', $id))
            ->when($request->grupo_trabajo_id, fn ($q, $id) => $q->where('grupo_trabajo_id', $id))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/pagos-extra/index', [
            'pagosExtra' => $pagosExtra,
            'cortes' => Corte::orderByDesc('semana')->get(),
            'gruposTrabajo' => GrupoTrabajo::where('activo', true)->orderBy('descripcion')->get(),
            'filters' => $request->only(['corte_id', 'grupo_trabajo_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/pagos-extra/create', [
            'tipos' => TipoPagoExtra::orderBy('orden')->get(),
            'cortes' => Corte::where('cerrado', false)->orderByDesc('semana')->get(),
            'gruposTrabajo' => GrupoTrabajo::where('activo', true)->orderBy('descripcion')->get(),
        ]);
    }

    public function store(PagoExtraStoreRequest $request): RedirectResponse
    {
        PagoExtra::create([
            'descripcion' => $request->descripcion,
            'tipo_id' => $request->tipo_id,
            'corte_id' => $request->corte_id,
            'grupo_trabajo_id' => $request->grupo_trabajo_id,
            'precio' => $request->precio,
            'dias' => $request->dias,
            'personas' => $request->personas,
        ]);

        return to_route('admin.prod.pagos-extra.index');
    }

    public function destroy(PagoExtra $pagoExtra): RedirectResponse
    {
        if ($pagoExtra->corte && $pagoExtra->corte->cerrado) {
            return back()->withErrors(['error' => 'No se puede eliminar un pago extra de un corte cerrado.']);
        }

        $pagoExtra->delete();

        return to_route('admin.prod.pagos-extra.index');
    }
}
