<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\ObraCobDatosRequest;
use App\Http\Requests\Admin\Cob\ObraCobUpdateRequest;
use App\Http\Requests\Admin\Cob\ObraEstadoRequest;
use App\Models\Obra;
use App\Models\Proyecto;
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

    /**
     * La obra ya no tiene página propia en cobranza: todo se gestiona desde el
     * hub del proyecto (Proyecto → Obra → Partida). Se redirige para no romper
     * enlaces antiguos. El presupuesto de la obra sigue en el módulo costos.
     */
    public function show(Obra $obra): RedirectResponse
    {
        abort_if($obra->es_planta, 404);

        return to_route('admin.cob.proyectos.show', $obra->proyecto_id);
    }

    public function updateFinancial(ObraCobUpdateRequest $request, Obra $obra): RedirectResponse
    {
        abort_if($obra->es_planta, 404);

        $obra->update($request->validated());

        return back();
    }

    /**
     * Cobranza cierra/reabre una obra. El estatus y `activa` se mantienen
     * sincronizados: el dashboard filtra por `activa`, los listados/PDF por
     * `estatus`.
     */
    public function cambiarEstado(ObraEstadoRequest $request, Obra $obra): RedirectResponse
    {
        abort_if($obra->es_planta, 404);

        $estatus = $request->validated('estatus');

        $obra->update([
            'estatus' => $estatus,
            'activa' => $estatus === 'abierta',
        ]);

        return back();
    }

    public function createObra(Proyecto $proyecto): Response
    {
        return Inertia::render('admin/cob/obras/create', [
            'proyecto' => $proyecto->only('id', 'no', 'descripcion'),
        ]);
    }

    /**
     * Crea una obra (normal o adicional) dentro del proyecto. El presupuesto
     * (obra_rubros) se auto-crea vía Obra::booted(); sus partidas quedan
     * pendientes. Las adicionales cuelgan de la obra base (ancla comercial).
     */
    public function storeObra(ObraCobDatosRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $tipo = $request->validated('tipo');

        $proyecto->obras()->create([
            'tipo' => $tipo,
            'no' => $request->validated('no'),
            'descripcion' => $request->validated('descripcion'),
            'obra_padre_id' => $tipo === 'adicional' ? $proyecto->obraBase?->id : null,
            'cliente_id' => $proyecto->cliente_id,
            'tipo_contrato' => $proyecto->tipo_contrato,
            'estatus' => 'abierta',
            'activa' => true,
        ]);

        return to_route('admin.cob.proyectos.show', $proyecto);
    }

    public function editObra(Proyecto $proyecto, Obra $obra): Response
    {
        abort_unless($obra->proyecto_id === $proyecto->id, 404);

        return Inertia::render('admin/cob/obras/edit', [
            'proyecto' => $proyecto->only('id', 'no', 'descripcion'),
            'obra' => $obra->only('id', 'no', 'descripcion', 'tipo', 'estatus'),
        ]);
    }

    public function updateObra(ObraCobDatosRequest $request, Proyecto $proyecto, Obra $obra): RedirectResponse
    {
        abort_unless($obra->proyecto_id === $proyecto->id, 404);

        $tipo = $request->validated('tipo');
        $estatus = $request->validated('estatus', $obra->estatus);

        $obra->update([
            'no' => $request->validated('no'),
            'descripcion' => $request->validated('descripcion'),
            'tipo' => $tipo,
            'obra_padre_id' => $tipo === 'adicional' ? $proyecto->obraBase?->id : null,
            // estatus y activa van sincronizados (igual que cambiarEstado).
            'estatus' => $estatus,
            'activa' => $estatus === 'abierta',
        ]);

        return to_route('admin.cob.proyectos.show', $proyecto);
    }

    public function reportePdf(Request $request): HttpResponse
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
                'anticipos',
                'comparativos',
                'deducciones',
            ])
            ->when($estatus !== 'todas', fn ($q) => $q->where('estatus', $estatus))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%")))
            ->orderBy('no')
            ->get();

        $pdf = Pdf::loadView('pdf.cob.reporte-obras', [
            'obras' => $obras,
            'estatusFiltro' => $estatus,
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
