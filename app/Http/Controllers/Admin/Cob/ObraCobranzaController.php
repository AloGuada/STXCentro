<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\ObraCobUpdateRequest;
use App\Models\Cliente;
use App\Models\Cob\DocumentoSeccion;
use App\Models\Obra;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ObraCobranzaController extends Controller
{
    public function index(Request $request): Response
    {
        $estatus = in_array($request->estatus, ['abierta', 'cerrada', 'todas'], true)
            ? $request->estatus
            : 'abierta';

        $obras = Obra::query()
            ->sinPlanta()
            ->with([
                'cliente',
                'partidas',
                'estimaciones.pagos',
                'estimaciones.historial',
                'anticipos',
                'comparativos',
                'deducciones',
            ])
            ->when($estatus !== 'todas', fn ($q) => $q->where('estatus', $estatus))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%")))
            ->orderBy('no')
            ->get();

        return Inertia::render('admin/cob/obras/index', [
            'obras' => $obras,
            'filters' => [
                'search' => $request->search,
                'estatus' => $estatus,
            ],
        ]);
    }

    public function show(Obra $obra): Response
    {
        abort_if($obra->es_planta, 404);

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
            'documentoCarpetas',
            'documentoArchivos',
        ]);

        $clientes = Cliente::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $documentoSecciones = DocumentoSeccion::query()
            ->activas()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'orden']);

        return Inertia::render('admin/cob/obras/show', [
            'obra' => $obra,
            'clientes' => $clientes,
            'documentoSecciones' => $documentoSecciones,
        ]);
    }

    public function updateFinancial(ObraCobUpdateRequest $request, Obra $obra): RedirectResponse
    {
        abort_if($obra->es_planta, 404);

        $obra->update($request->validated());

        return back();
    }

    public function reportePdf(): HttpResponse
    {
        $obras = Obra::query()
            ->sinPlanta()
            ->with([
                'cliente',
                'partidas',
                'estimaciones.pagos',
                'anticipos',
                'comparativos',
                'deducciones',
            ])
            ->orderBy('no')
            ->get();

        $pdf = Pdf::loadView('pdf.cob.reporte-obras', [
            'obras' => $obras,
        ])->setPaper('letter', 'landscape');

        return $pdf->download('reporte-obras-cobranza.pdf');
    }

    public function estadoCuentaPdf(Obra $obra): HttpResponse
    {
        abort_if($obra->es_planta, 404);

        $obra->load([
            'cliente',
            'partidas',
            'estimaciones.pagos',
            'estimaciones.historial',
            'anticipos',
            'comparativos',
            'deducciones',
        ]);

        $pdf = Pdf::loadView('pdf.cob.estado-cuenta-obra', [
            'obra' => $obra,
        ])->setPaper('letter', 'landscape');

        $filename = "estado-cuenta-{$obra->no}.pdf";

        return $pdf->download($filename);
    }
}
