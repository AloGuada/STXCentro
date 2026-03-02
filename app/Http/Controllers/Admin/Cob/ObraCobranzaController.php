<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\ObraCobUpdateRequest;
use App\Models\Cliente;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ObraCobranzaController extends Controller
{
    public function index(Request $request): Response
    {
        $obras = Obra::query()
            ->with([
                'cliente',
                'partidas',
                'estimaciones.pagos',
                'estimaciones.historial',
                'anticipos',
                'comparativos',
                'deducciones',
            ])
            ->when($request->search, fn ($q, $s) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%"))
            ->orderBy('no')
            ->get();

        return Inertia::render('admin/cob/obras/index', [
            'obras' => $obras,
            'filters' => $request->only('search'),
        ]);
    }

    public function show(Obra $obra): Response
    {
        $obra->load([
            'cliente',
            'partidas',
            'estimaciones.pagos',
            'estimaciones.historial.usuario',
            'estimaciones.retenciones.tipoRetencion',
            'estimaciones.documentos.configuracionDocumento',
            'anticipos',
            'adendas',
            'comparativos',
            'deducciones',
            'eventos.children',
            'disputas',
            'penalizaciones',
            'configuracionDocumentos',
        ]);

        $clientes = Cliente::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('admin/cob/obras/show', [
            'obra' => $obra,
            'clientes' => $clientes,
        ]);
    }

    public function updateFinancial(ObraCobUpdateRequest $request, Obra $obra): RedirectResponse
    {
        $obra->update($request->validated());

        return back();
    }
}
