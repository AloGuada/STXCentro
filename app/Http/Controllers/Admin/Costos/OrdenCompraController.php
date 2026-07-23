<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\OrdenCompraEstatus;
use App\Exports\Costos\OrdenesCompraExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\CancelarRequest;
use App\Http\Requests\Admin\Costos\OrdenCompraStoreRequest;
use App\Models\Costos\Factura;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Presupuesto;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Services\Costos\CfdiXmlParser;
use App\Services\Costos\ComparativoTotalesBuilder;
use App\Services\Costos\FirmasPdfBuilder;
use App\Services\Costos\RetencionCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class OrdenCompraController extends Controller
{
    public function index(Request $request): Response
    {
        $ordenes = OrdenCompra::query()
            ->with([
                'proveedor:id,razon_social,nombre_comercial',
                'departamento:id,descripcion',
                'detalles:id,orden_compra_id,obra_rubro_id,descripcion,unidad,cantidad,precio_unitario,subtotal',
                'detalles.obraRubro.presupuesto.presupuestable',
                'entregas.detalles.devoluciones',
                'facturas.pago',
                'solicitudesPago:id,orden_compra_id,folio,estatus',
            ])
            ->withCount(['facturas', 'entregas', 'detalles'])
            ->addSelect([
                'pagos_count' => DB::table('costos_pagos')
                    ->join('costos_facturas', function ($join) {
                        $join->on('costos_facturas.id', '=', 'costos_pagos.pagable_id')
                            ->where('costos_pagos.pagable_type', 'App\\Models\\Costos\\Factura');
                    })
                    ->whereColumn('costos_facturas.orden_compra_id', 'costos_ordenes_compra.id')
                    ->whereNull('costos_pagos.pago_padre_id')
                    ->selectRaw('count(*)'),
            ])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('folio', 'like', "%{$search}%")
                        ->orWhereHas('proveedor', fn ($p) => $p->where('razon_social', 'like', "%{$search}%"))
                        ->orWhereHas('detalles', fn ($d) => $d->where('descripcion', 'like', "%{$search}%"));
                });
            })
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->when($request->proveedor_id, fn ($q, $id) => $q->where('proveedor_id', $id))
            ->when($request->tipo_pago, fn ($q, $tp) => $q->where('tipo_pago', $tp))
            ->when($request->presupuesto_id, function ($q, $presupuestoId) {
                // La OC carga a un presupuesto vía el centro de costos de sus detalles.
                $q->whereHas('detalles.obraRubro', fn ($or) => $or->where('presupuesto_id', $presupuestoId));
            })
            // Los usuarios comunes solo ven las OC de sus propias requisiciones; los
            // operativos del módulo (compras, costos, almacén, contabilidad) y el
            // super-admin ven todas.
            ->unless($request->user()->can('costos.ordenes-compra.ver-todas'), function ($q) use ($request) {
                $q->whereHas('requisicion', fn ($r) => $r->where('solicitante_id', $request->user()->id));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $ordenes->getCollection()->each->append(['pagada_anticipo_contado', 'presupuesto_label']);

        return Inertia::render('admin/costos/ordenes-compra/index', [
            'ordenes' => $ordenes,
            'filters' => $request->only('search', 'estatus', 'proveedor_id', 'presupuesto_id', 'tipo_pago'),
            'proveedoresFiltro' => $this->proveedoresConOrdenes(),
            'presupuestosFiltro' => $this->presupuestosConOrdenes(),
        ]);
    }

    /**
     * Compras sube la factura (CFDI XML + PDF) de una OC de contado ya pagada.
     * El CFDI se parsea solo para completar los datos fiscales (UUID, impuestos,
     * total); no genera un segundo pago (el anticipo ya cubrió la OC). Requiere
     * recepción previa del almacén.
     */
    public function subirFacturaContado(Request $request, OrdenCompra $ordenCompra, CfdiXmlParser $parser): RedirectResponse
    {
        Gate::authorize('costos.facturas.crear');

        if (($ordenCompra->tipo_pago?->value ?? null) !== 'contado') {
            return back()->withErrors(['xml' => 'Esta acción es solo para órdenes de compra de contado.']);
        }

        if (! $ordenCompra->entregas()->exists()) {
            return back()->withErrors(['xml' => 'No se puede facturar hasta registrar la recepción del almacén.']);
        }

        $validated = $request->validate([
            'xml' => ['required', 'file', 'mimes:xml', 'max:5120'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'notas' => ['nullable', 'string'],
        ]);

        try {
            $fiscal = $parser->parse(file_get_contents($request->file('xml')->getRealPath()) ?: '');
        } catch (\Throwable $e) {
            return back()->withErrors(['xml' => 'No se pudo leer el CFDI: '.$e->getMessage()]);
        }

        if (! empty($fiscal['uuid_fiscal']) && Factura::where('uuid_fiscal', $fiscal['uuid_fiscal'])->exists()) {
            return back()->withErrors(['xml' => 'Ya existe una factura registrada con ese UUID fiscal.']);
        }

        $saldoFacturable = (float) $ordenCompra->saldo_facturable;
        $totalCfdi = (float) ($fiscal['total'] ?? 0);
        if ($totalCfdi > $saldoFacturable + (float) config('costos.epsilon_monto')) {
            return back()->withErrors(['xml' => sprintf(
                'El total del CFDI ($%s) excede el saldo facturable de la OC ($%s). Verifica que el XML corresponda a esta orden.',
                number_format($totalCfdi, 2),
                number_format($saldoFacturable, 2),
            )]);
        }

        // El anticipo de contado ya pagó la OC: la factura es solo el comprobante
        // fiscal, así que nace Pagada (no entra al ciclo aprobación → pago). Si por
        // alguna razón el anticipo aún no está pagado, cae al flujo normal.
        $anticipoPagado = $ordenCompra->pagadaAnticipoContado();
        $userId = $request->user()->id;

        $factura = DB::transaction(function () use ($ordenCompra, $fiscal, $request, $validated, $anticipoPagado, $userId) {
            $factura = Factura::create([
                'orden_compra_id' => $ordenCompra->id,
                'proveedor_id' => $ordenCompra->proveedor_id,
                'uuid_fiscal' => $fiscal['uuid_fiscal'] ?? null,
                'folio_fiscal' => $fiscal['folio_fiscal'] ?? null,
                'subtotal' => $fiscal['subtotal'] ?? 0,
                'iva' => $fiscal['iva_trasladado'] ?? 0,
                'iva_trasladado' => $fiscal['iva_trasladado'] ?? 0,
                'iva_retenido' => $fiscal['iva_retenido'] ?? 0,
                'isr_retenido' => $fiscal['isr_retenido'] ?? 0,
                'impuestos_detalle' => $fiscal['impuestos_detalle'] ?? null,
                'total' => $fiscal['total'] ?? 0,
                'moneda' => $ordenCompra->moneda,
                'tipo_cambio' => $ordenCompra->tipo_cambio,
                'metodo_pago' => $fiscal['metodo_pago'] ?? null,
                'forma_pago' => $fiscal['forma_pago'] ?? null,
                'fecha_factura' => $fiscal['fecha_factura'] ?? null,
                'estatus' => $anticipoPagado ? FacturaEstatus::Pagada->value : FacturaEstatus::PendienteAprobacion->value,
                'aprobada_costos' => $anticipoPagado,
                'aprobada_costos_por' => $anticipoPagado ? $userId : null,
                'aprobada_costos_at' => $anticipoPagado ? now() : null,
                'aceptada_contabilidad' => $anticipoPagado,
                'aceptada_contabilidad_por' => $anticipoPagado ? $userId : null,
                'aceptada_contabilidad_at' => $anticipoPagado ? now() : null,
                'notas' => $validated['notas'] ?? null,
            ]);

            $dir = "facturas/{$ordenCompra->proveedor_id}/{$factura->id}";

            $xmlPath = $request->file('xml')->storeAs($dir, 'cfdi.xml', 'public');
            $factura->media()->create([
                'descripcion' => DocumentoTipo::XmlFactura->value,
                'nombre_original' => $request->file('xml')->getClientOriginalName(),
                'path' => $xmlPath,
                'mime' => 'application/xml',
                'size' => Storage::disk('public')->size($xmlPath),
            ]);

            $pdfPath = $request->file('pdf')->storeAs($dir, 'cfdi.pdf', 'public');
            $factura->media()->create([
                'descripcion' => DocumentoTipo::PdfFactura->value,
                'nombre_original' => $request->file('pdf')->getClientOriginalName(),
                'path' => $pdfPath,
                'mime' => 'application/pdf',
                'size' => Storage::disk('public')->size($pdfPath),
            ]);

            return $factura;
        });

        $ordenCompra->recalcularEstatus();

        return back()->with('success', "Factura {$factura->folio} registrada desde el CFDI.");
    }

    /**
     * Exporta a Excel el listado filtrado, aplanado a una línea por
     * (orden de compra × producto).
     */
    public function exportar(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        Gate::authorize('costos.ordenes-compra.ver-todas');

        $filtros = $request->only('search', 'estatus', 'proveedor_id', 'presupuesto_id', 'tipo_pago');

        return Excel::download(
            new OrdenesCompraExport($filtros),
            'ordenes-compra-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    /**
     * Proveedores que tienen al menos una orden de compra, para el filtro.
     *
     * @return \Illuminate\Support\Collection<int, Proveedor>
     */
    private function proveedoresConOrdenes(): \Illuminate\Support\Collection
    {
        $ids = OrdenCompra::query()->distinct()->pluck('proveedor_id')->filter();

        return Proveedor::whereIn('id', $ids)
            ->orderBy('razon_social')
            ->get(['id', 'razon_social', 'nombre_comercial']);
    }

    /**
     * Presupuestos referenciados por alguna orden de compra (vía sus detalles),
     * para el filtro del índice.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, label: string}>
     */
    private function presupuestosConOrdenes(): \Illuminate\Support\Collection
    {
        $ids = DB::table('costos_ordenes_compra_detalle as ocd')
            ->join('costos_obra_rubros as orub', 'orub.id', '=', 'ocd.obra_rubro_id')
            ->whereNotNull('orub.presupuesto_id')
            ->distinct()
            ->pluck('orub.presupuesto_id');

        return Presupuesto::with('presupuestable')
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (Presupuesto $p) => ['id' => $p->id, 'label' => $p->nombreMostrar()])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function create(): Response
    {
        Gate::authorize('costos.ordenes-compra.crear');

        return Inertia::render('admin/costos/ordenes-compra/create', [
            'proveedores' => Proveedor::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
            'obras' => Obra::orderBy('no')->get(['id', 'no', 'descripcion']),
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'obraRubros' => ObraRubro::with('rubro')->get(),
        ]);
    }

    public function store(OrdenCompraStoreRequest $request): RedirectResponse
    {
        Gate::authorize('costos.ordenes-compra.crear');

        $warnings = [];
        $oc = null;

        DB::transaction(function () use ($request, &$warnings, &$oc) {
            $oc = OrdenCompra::create([
                ...$request->safe()->except(['detalles', 'archivo']),
                'creado_por' => $request->user()->id,
                'estatus' => 'pendiente_entrega',
            ]);

            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $oc->media()->create([
                    'descripcion' => DocumentoTipo::OcArchivo->value,
                    'nombre_original' => $file->getClientOriginalName(),
                    'path' => $file->store('costos/ordenes-compra', 'public'),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            $obraRubros = ObraRubro::with('rubro:id,codigo')
                ->findMany(collect($request->input('detalles', []))->pluck('obra_rubro_id')->filter()->unique())
                ->keyBy('id');

            foreach ($request->input('detalles', []) as $detalle) {
                $cantidad = (float) $detalle['cantidad'];
                $precioUnitario = (float) $detalle['precio_unitario'];
                $subtotal = round($cantidad * $precioUnitario, 2);

                $oc->detalles()->create([
                    'obra_rubro_id' => $detalle['obra_rubro_id'],
                    'descripcion' => $detalle['descripcion'],
                    'unidad' => $detalle['unidad'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $subtotal,
                ]);

                $obraRubro = $obraRubros->get((int) $detalle['obra_rubro_id']);
                if ($obraRubro) {
                    $disponible = $obraRubro->disponible;
                    if ($subtotal > $disponible) {
                        $warnings[] = "El centro de costos {$obraRubro->rubro?->codigo} excede el presupuesto disponible.";
                    }
                }
            }

            $oc->load('detalles');
            $oc->aplicarImpactoPresupuestal();
        });

        $redirect = to_route('admin.costos.ordenes-compra.show', $oc);

        if (count($warnings) > 0) {
            $redirect->with('warning', implode(' ', $warnings));
        }

        return $redirect;
    }

    /**
     * Solo ve la OC quien puede ver todas, quien la creó, o el solicitante
     * de la requisición que la originó (acceso de solo lectura por propiedad).
     */
    private function autorizarVer(OrdenCompra $ordenCompra): void
    {
        $ordenCompra->loadMissing('requisicion:id,solicitante_id');
        $user = auth()->user();
        abort_unless(
            $user->can('costos.ordenes-compra.ver-todas')
                || $ordenCompra->creado_por === $user->id
                || $ordenCompra->requisicion?->solicitante_id === $user->id,
            403,
        );
    }

    public function show(OrdenCompra $ordenCompra, RetencionCalculator $retenciones): Response
    {
        $this->autorizarVer($ordenCompra);

        $ordenCompra->load([
            'proveedor.regimenFiscal:id,clave,descripcion',
            'obra',
            'departamento',
            'creador',
            'detalles.obraRubro.rubro',
            'detalles.obraRubro.obra',
            'detalles.usoCfdi:id,clave,descripcion',
            'entregas.detalles.ordenCompraDetalle:id,descripcion,unidad,cantidad,precio_unitario',
            'entregas.detalles.devoluciones',
            'entregas.recibidoPor:id,name',
            'entregas.media',
            'facturas.media',
            'facturas.pago.media',
            'facturas.notasCredito.media',
            'facturas.entregas.media',
            'media',
            'rubrosAfectados.obraRubro.rubro',
            'solicitudesPago:id,orden_compra_id,folio,estatus',
            'solicitudesPago.pago.media',
            'activities.causer',
        ]);

        $ordenCompra->append(['total_facturado', 'total_pagado', 'saldo_pendiente', 'pagada_anticipo_contado']);

        $lineas = $ordenCompra->detalles->map(fn ($d) => [
            'tipo_fiscal' => $d->tipo_fiscal?->value ?? 'mercancia',
            'subtotal' => (float) $d->subtotal,
        ]);

        return Inertia::render('admin/costos/ordenes-compra/show', [
            'ordenCompra' => $ordenCompra,
            'retenciones' => $ordenCompra->proveedor
                ? $retenciones->calcular($ordenCompra->proveedor, $lineas)
                : null,
        ]);
    }

    public function destroy(OrdenCompra $ordenCompra): RedirectResponse
    {
        Gate::authorize('costos.ordenes-compra.eliminar');

        if ($ordenCompra->facturas()->exists()) {
            return back()->withErrors(['estatus' => 'No se puede eliminar una orden con facturas asociadas.']);
        }

        DB::transaction(function () use ($ordenCompra) {
            if (in_array($ordenCompra->estatus, [OrdenCompraEstatus::PendienteEntrega, OrdenCompraEstatus::PendienteFactura, OrdenCompraEstatus::PendienteAprobacion], true)) {
                $ordenCompra->load('detalles');
                $ordenCompra->revertirImpactoPresupuestal();
            }

            $ordenCompra->detalles()->delete();
            $ordenCompra->delete();
        });

        return to_route('admin.costos.ordenes-compra.index');
    }

    public function cancelar(CancelarRequest $request, OrdenCompra $ordenCompra): RedirectResponse
    {
        Gate::authorize('costos.ordenes-compra.cancelar');

        if (! in_array($ordenCompra->estatus, [OrdenCompraEstatus::PendienteEntrega, OrdenCompraEstatus::PendienteFactura, OrdenCompraEstatus::PendienteAprobacion], true)) {
            return back()->withErrors(['estatus' => 'Solo se pueden cancelar órdenes pendientes.']);
        }

        if ($ordenCompra->facturas()->where('estatus', '!=', FacturaEstatus::Cancelada->value)->exists()) {
            return back()->withErrors(['estatus' => 'No se puede cancelar una orden con facturas activas. Cancele primero las facturas.']);
        }

        DB::transaction(function () use ($ordenCompra, $request) {
            $ordenCompra->load('detalles');
            $ordenCompra->revertirImpactoPresupuestal();
            $ordenCompra->transitionTo(OrdenCompraEstatus::Cancelada);
            $ordenCompra->registrarCancelacion($request->validated('motivo'), $request->user()->id);
        });

        return back()->with('success', 'Orden de compra cancelada.');
    }

    public function pdfOc(Request $request, OrdenCompra $ordenCompra): HttpResponse
    {
        $this->autorizarVer($ordenCompra);

        $ordenCompra->load([
            'proveedor',
            'departamento',
            'detalles.usoCfdi:id,clave',
            'detalles.obraRubro.obra:id,no',
            'requisicion:id,folio',
        ]);

        $pdf = Pdf::loadView('pdf.costos.formato-orden-compra', [
            'oc' => $ordenCompra,
        ])->setPaper('letter', 'portrait');

        $filename = "OC-{$ordenCompra->folio}.pdf";

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    public function pdfRequisicion(OrdenCompra $ordenCompra): HttpResponse
    {
        $this->autorizarVer($ordenCompra);

        $requisicion = $ordenCompra->requisicion;
        abort_if(! $requisicion, 404, 'Esta OC no tiene requisición de origen.');

        $requisicion->load([
            'solicitante',
            'departamento',
            'detalles.cotizaciones.opcion',
            'detalles.selecciones.cotizacionPrecio',
            'detalles.selecciones.proveedor:id,razon_social,tipo_persona,regimen_fiscal_id',
            'detalles.selecciones.proveedor.regimenFiscal:id,clave',
            'detalles.obraRubro.obra:id,no,descripcion',
            'detalles.obraRubro.rubro:id,codigo,descripcion',
            'cotizacionOpciones.proveedor:id,razon_social,nombre_comercial',
        ]);

        // Columnas de firma: solo los niveles que aplican al tipo de documento
        // (requisición) y al departamento de la requisición.
        $aprobaciones = $requisicion->aprobaciones()
            ->with('aprobador')
            ->get();

        $firmas = app(FirmasPdfBuilder::class)->build(
            $requisicion->tipoAprobacion(),
            $requisicion->departamento_id,
            $aprobaciones,
        );

        $pdf = Pdf::loadView('pdf.costos.formato-requisicion-comparativo', [
            'requisicion' => $requisicion,
            'firmas' => $firmas,
            'totales' => app(ComparativoTotalesBuilder::class)->build($requisicion),
        ])->setPaper('letter', 'landscape');

        return $pdf->stream("Comparativo-{$requisicion->folio}.pdf");
    }

    public function pdfContrarecibo(OrdenCompra $ordenCompra, Factura $factura): HttpResponse
    {
        $this->autorizarVer($ordenCompra);

        abort_if($factura->orden_compra_id !== $ordenCompra->id, 404);

        $ordenCompra->load('proveedor');

        $pago = $factura->pago;
        $fechaPago = $pago?->fecha_pago_programada;

        $pdf = Pdf::loadView('pdf.costos.formato-contrarecibo', [
            'oc' => $ordenCompra,
            'factura' => $factura,
            'fechaPago' => $fechaPago,
        ])->setPaper('letter', 'portrait');

        return $pdf->stream("Contrarecibo-{$factura->folio}.pdf");
    }
}
