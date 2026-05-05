<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\NotaCreditoEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\CancelarRequest;
use App\Models\Costos\NotaCredito;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// El alta de notas de crédito vive en el portal del proveedor
// (PortalNotaCreditoController). Admin solo lee y cancela.
class NotaCreditoController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('costos.notas-credito.ver');

        $notas = NotaCredito::query()
            ->with(['factura:id,folio,proveedor_id', 'factura.proveedor:id,razon_social', 'creador:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($qq) use ($s) {
                $qq->where('folio', 'like', "%{$s}%")
                    ->orWhere('uuid_fiscal', 'like', "%{$s}%")
                    ->orWhere('concepto', 'like', "%{$s}%");
            }))
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->when($request->factura_id, fn ($q, $f) => $q->where('factura_id', $f))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/costos/notas-credito/index', [
            'notas' => $notas,
            'filters' => $request->only('search', 'estatus', 'factura_id'),
        ]);
    }

    public function show(NotaCredito $notaCredito): Response
    {
        Gate::authorize('costos.notas-credito.ver');

        $notaCredito->load([
            'factura:id,folio,total,proveedor_id',
            'factura.proveedor:id,razon_social',
            'creador:id,name',
            'media',
            'mediaXml',
            'mediaPdf',
            'activities.causer',
        ]);

        return Inertia::render('admin/costos/notas-credito/show', [
            'nota' => $notaCredito,
        ]);
    }

    public function cancelar(CancelarRequest $request, NotaCredito $notaCredito): RedirectResponse
    {
        Gate::authorize('costos.notas-credito.cancelar');

        if ($notaCredito->estatus !== NotaCreditoEstatus::Vigente) {
            return back()->withErrors(['estatus' => 'Solo se pueden cancelar notas de crédito vigentes.']);
        }

        DB::transaction(function () use ($notaCredito, $request) {
            $notaCredito->update(['motivo_cancelacion' => $request->validated('motivo')]);
            $notaCredito->transitionTo(NotaCreditoEstatus::Cancelada);
        });

        return back()->with('success', 'Nota de crédito cancelada.');
    }
}
