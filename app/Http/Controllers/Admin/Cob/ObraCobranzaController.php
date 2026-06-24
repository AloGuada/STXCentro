<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\ObraCobDatosRequest;
use App\Http\Requests\Admin\Cob\ObraCobUpdateRequest;
use App\Http\Requests\Admin\Cob\ObraEstadoRequest;
use App\Models\Cliente;
use App\Models\Obra;
use App\Models\Proyecto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
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
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->whereLike('no', "%{$s}%")
                ->orWhereLike('descripcion', "%{$s}%")))
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
     * Página de la obra: concentra contrato/datos financieros, partidas,
     * estimaciones y demás conceptos de cobranza de ESTA obra. El proyecto solo
     * es el paraguas + Gantt. El presupuesto vive en el módulo costos.
     */
    public function show(Obra $obra): Response
    {
        abort_if($obra->es_planta, 404);

        $obra->load([
            'cliente',
            'proyecto',
            'partidas',
            'estimaciones.pagos',
            'estimaciones.historial.usuario',
            'estimaciones.retenciones.tipoRetencion',
            'anticipos',
            'adendas',
            'comparativos',
            'deducciones',
            'disputas',
            'penalizaciones',
            'eventos.children',
        ]);

        return Inertia::render('admin/cob/obras/show', [
            'obra' => $obra,
            'clientes' => $this->clientes(),
        ]);
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
     * Crea una obra (normal o adicional) dentro del proyecto. Las adicionales
     * son obras hermanas (mismo proyecto, tipo `adicional`); ya no cuelgan de la
     * obra base. El presupuesto (obra_rubros) se auto-crea vía Obra::booted() y
     * sus partidas quedan pendientes.
     */
    public function storeObra(ObraCobDatosRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $proyecto->obras()->create([
            'tipo' => $request->validated('tipo'),
            'no' => $request->validated('no'),
            'descripcion' => $request->validated('descripcion'),
            'cliente_id' => $proyecto->cliente_id,
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

        $estatus = $request->validated('estatus', $obra->estatus);

        $obra->update([
            'no' => $request->validated('no'),
            'descripcion' => $request->validated('descripcion'),
            'tipo' => $request->validated('tipo'),
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
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->whereLike('no', "%{$s}%")
                ->orWhereLike('descripcion', "%{$s}%")))
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

    /** @return Collection<int, Cliente> */
    private function clientes(): Collection
    {
        return Cliente::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }
}
