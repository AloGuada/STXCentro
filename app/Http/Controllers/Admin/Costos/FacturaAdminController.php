<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\FacturaEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\CancelarRequest;
use App\Http\Requests\Admin\Costos\FacturaStoreRequest;
use App\Mail\FacturaAceptadaMail;
use App\Mail\PagoProgramadoMail;
use App\Models\Costos\Factura;
use App\Models\Costos\FacturaDetalle;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Pago;
use App\Models\Proveedor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use setasign\Fpdi\Fpdi;

class FacturaAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $facturas = Factura::query()
            ->with(['proveedor:id,razon_social,nombre_comercial', 'ordenCompra:id,folio', 'mediaPdf'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('folio', 'like', "%{$search}%")
                        ->orWhereHas('proveedor', fn ($p) => $p->where('razon_social', 'like', "%{$search}%"));
                });
            })
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->when($request->orden_compra_id, fn ($q, $id) => $q->where('orden_compra_id', $id))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/costos/facturas/index', [
            'facturas' => $facturas,
            'filters' => $request->only('search', 'estatus', 'orden_compra_id'),
            'proveedores' => Proveedor::query()->orderBy('razon_social')->get(['id', 'razon_social']),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('costos.facturas.crear');

        $ordenes = OrdenCompra::query()
            ->whereIn('estatus', [
                FacturaEstatus::PendienteEntrega->value,
                'pendiente_factura',
                'pendiente_entrega',
                'pendiente_aprobacion',
                'pendiente_pago',
            ])
            ->with('proveedor:id,razon_social')
            ->orderByDesc('id')
            ->get(['id', 'folio', 'proveedor_id', 'moneda', 'total']);

        $ordenCompra = null;
        if ($request->filled('orden_compra_id')) {
            $ordenCompra = OrdenCompra::with([
                'proveedor:id,razon_social,nombre_comercial',
                'detalles.obraRubro.rubro',
                'detalles.obraRubro.obra',
                'entregas.detalles',
                'facturas' => fn ($q) => $q->where('estatus', '!=', FacturaEstatus::Cancelada->value),
                'facturas.detalles',
            ])->find($request->integer('orden_compra_id'));
        }

        return Inertia::render('admin/costos/facturas/create', [
            'ordenes' => $ordenes,
            'ordenCompra' => $ordenCompra,
        ]);
    }

    public function store(FacturaStoreRequest $request): RedirectResponse
    {
        Gate::authorize('costos.facturas.crear');

        $oc = OrdenCompra::with(['detalles', 'facturas' => fn ($q) => $q->where('estatus', '!=', FacturaEstatus::Cancelada->value), 'facturas.detalles'])
            ->findOrFail($request->integer('orden_compra_id'));

        // Valida que cada partida pertenezca a esta OC y que no se sobre-facture.
        $ocdIds = $oc->detalles->pluck('id')->all();
        $yaFacturadoPorPartida = [];
        foreach ($oc->facturas as $f) {
            foreach ($f->detalles as $fd) {
                $yaFacturadoPorPartida[$fd->orden_compra_detalle_id]
                    = ($yaFacturadoPorPartida[$fd->orden_compra_detalle_id] ?? 0) + (float) $fd->cantidad;
            }
        }

        $enviandoPorPartida = [];
        foreach ($request->input('detalles') as $i => $d) {
            $ocdId = (int) $d['orden_compra_detalle_id'];

            if (! in_array($ocdId, $ocdIds, true)) {
                return back()->withErrors([
                    "detalles.{$i}.orden_compra_detalle_id" => 'La partida no pertenece a esta OC.',
                ])->withInput();
            }

            $enviandoPorPartida[$ocdId] = ($enviandoPorPartida[$ocdId] ?? 0) + (float) $d['cantidad'];

            $ocd = $oc->detalles->firstWhere('id', $ocdId);
            $saldoFacturable = (float) $ocd->cantidad - ($yaFacturadoPorPartida[$ocdId] ?? 0);

            if ($enviandoPorPartida[$ocdId] > $saldoFacturable + 0.001) {
                return back()->withErrors([
                    "detalles.{$i}.cantidad" => sprintf(
                        'Excede el saldo facturable (%.2f %s) de la partida "%s".',
                        $saldoFacturable, $ocd->unidad, $ocd->descripcion,
                    ),
                ])->withInput();
            }
        }

        DB::transaction(function () use ($request, $oc) {
            $subtotal = 0;
            $detalles = [];
            foreach ($request->input('detalles') as $d) {
                $sub = round((float) $d['cantidad'] * (float) $d['precio_unitario'], 2);
                $subtotal += $sub;
                $detalles[] = $d + ['subtotal' => $sub];
            }

            $iva = (float) $request->input('iva', 0);
            $total = round($subtotal + $iva, 2);

            $factura = Factura::create([
                'orden_compra_id' => $oc->id,
                'proveedor_id' => $oc->proveedor_id,
                'uuid_fiscal' => $request->input('uuid_fiscal'),
                'folio_fiscal' => $request->input('folio_fiscal'),
                'subtotal' => $subtotal,
                'iva' => $iva,
                'total' => $total,
                'moneda' => $request->input('moneda', $oc->moneda),
                'fecha_factura' => $request->input('fecha_factura'),
                'notas' => $request->input('notas'),
                'estatus' => 'pendiente_entrega',
            ]);

            foreach ($detalles as $d) {
                FacturaDetalle::create([
                    'factura_id' => $factura->id,
                    'orden_compra_detalle_id' => $d['orden_compra_detalle_id'],
                    'cantidad' => $d['cantidad'],
                    'precio_unitario' => $d['precio_unitario'],
                    'subtotal' => $d['subtotal'],
                ]);
            }

            $oc->recalcularEstatus();
        });

        return to_route('admin.costos.facturas.index')
            ->with('success', 'Factura creada correctamente.');
    }

    public function show(Factura $factura): Response
    {
        $factura->load([
            'proveedor',
            'ordenCompra.detalles.obraRubro.rubro',
            'detalles.ordenCompraDetalle',
            'entregas.recibidoPor',
            'pago.pagosParciales',
            'aprobadaCostosPor',
            'aceptadaContabilidadPor',
            'activities.causer',
        ]);

        return Inertia::render('admin/costos/facturas/show', [
            'factura' => $factura,
        ]);
    }

    public function aprobarCostos(Request $request, Factura $factura): RedirectResponse
    {
        if ($factura->estatus !== FacturaEstatus::PendienteAprobacion) {
            return back()->withErrors(['estatus' => 'La factura debe tener la entrega completa para ser aprobada.']);
        }

        if ($factura->aprobada_costos) {
            return back()->withErrors(['aprobada_costos' => 'La factura ya fue aprobada por costos.']);
        }

        DB::transaction(function () use ($request, $factura) {
            $factura->update([
                'aprobada_costos' => true,
                'aprobada_costos_por' => $request->user()->id,
                'aprobada_costos_at' => now(),
                'estatus' => 'pendiente_pago',
            ]);

            $factura->ordenCompra->recalcularEstatus();
        });

        return back()->with('success', 'Factura aprobada por costos.');
    }

    public function aceptarContabilidad(Request $request, Factura $factura): RedirectResponse
    {
        Gate::authorize('costos.facturas.aceptar-contabilidad');

        if ($factura->estatus !== FacturaEstatus::PendientePago) {
            return back()->withErrors(['estatus' => 'La factura debe estar pendiente de pago.']);
        }

        if (! $factura->aprobada_costos) {
            return back()->withErrors(['aprobada_costos' => 'La factura debe estar aprobada por costos.']);
        }

        if ($factura->aceptada_contabilidad) {
            return back()->withErrors(['aceptada_contabilidad' => 'La factura ya fue aceptada por contabilidad.']);
        }

        $pago = DB::transaction(function () use ($request, $factura) {
            $proveedor = $factura->proveedor;

            // Copia dias_credito del proveedor si la factura no tiene override.
            if ($factura->dias_credito === null) {
                $factura->dias_credito = $proveedor?->dias_credito_default ?? 0;
            }

            $factura->fill([
                'aceptada_contabilidad' => true,
                'aceptada_contabilidad_por' => $request->user()->id,
                'aceptada_contabilidad_at' => now(),
                'fecha_pago_calculada' => $factura->calcularFechaPago() ?? Carbon::today(),
            ])->save();

            $tipoPago = $proveedor && $proveedor->maneja_credito ? 'credito' : 'contado';

            $pago = Pago::create([
                'pagable_type' => Factura::class,
                'pagable_id' => $factura->id,
                'monto_pago' => $factura->total,
                'moneda' => $factura->moneda,
                'tipo_pago' => $tipoPago,
                'fecha_pago_programada' => $factura->fecha_pago_calculada,
                'estatus' => 'programado',
            ]);

            if ($proveedor && $proveedor->email) {
                Mail::to($proveedor->email)->send(new FacturaAceptadaMail($factura, $proveedor));
                Mail::to($proveedor->email)->send(new PagoProgramadoMail($pago, $proveedor));
            }

            $factura->ordenCompra->recalcularEstatus();

            return $pago;
        });

        return back()->with('success', 'Factura aceptada y pago programado para '.$pago->fecha_pago_programada->format('d/m/Y').'.');
    }

    public function cancelar(CancelarRequest $request, Factura $factura): RedirectResponse
    {
        Gate::authorize('costos.facturas.cancelar');

        if (in_array($factura->estatus, [FacturaEstatus::Pagada, FacturaEstatus::Cancelada], true)) {
            return back()->withErrors(['estatus' => 'La factura ya está '.$factura->estatus->value.'.']);
        }

        if ($factura->aceptada_contabilidad) {
            return back()->withErrors(['estatus' => 'No se puede cerrar una factura con pago programado. Cancele primero el pago.']);
        }

        DB::transaction(function () use ($factura, $request) {
            $factura->transitionTo(FacturaEstatus::Cancelada);
            $factura->registrarCancelacion($request->validated('motivo'), $request->user()->id);
            $factura->ordenCompra?->recalcularEstatus();
        });

        return back()->with('success', 'Factura cerrada.');
    }

    public function reporteSemanal(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $request->validate([
            'anio' => 'required|integer|min:2020|max:2100',
            'semana' => 'required|integer|min:1|max:53',
        ]);

        $anio = (int) $request->anio;
        $semana = (int) $request->semana;

        $inicioSemana = Carbon::now()->setISODate($anio, $semana)->startOfWeek();
        $finSemana = (clone $inicioSemana)->endOfWeek();

        $facturas = Factura::query()
            ->with(['proveedor:id,razon_social', 'ordenCompra:id,folio', 'ordenCompra.pdfFirmado', 'ordenCompra.pdfFormato', 'ordenCompra.archivo', 'mediaPdf', 'entregas.media'])
            ->whereHas('entregas', fn ($q) => $q->whereBetween('fecha_entrega', [$inicioSemana->toDateString(), $finSemana->toDateString()]))
            ->orderBy('orden_compra_id')
            ->orderBy('fecha_factura')
            ->get();

        if ($facturas->isEmpty()) {
            return back()->withErrors(['semana' => 'No se encontraron facturas en la semana seleccionada.']);
        }

        // Generar resumen PDF con DomPDF
        $resumenPdf = Pdf::loadView('pdf.costos.reporte-semanal-facturas', [
            'facturas' => $facturas,
            'semana' => $semana,
            'anio' => $anio,
            'fechaInicio' => $inicioSemana->format('d/m/Y'),
            'fechaFin' => $finSemana->format('d/m/Y'),
        ])->setPaper('letter', 'landscape')
            ->setOption('margin-top', 30)
            ->setOption('margin-bottom', 60)
            ->setOption('margin-left', 60)
            ->setOption('margin-right', 60);

        // Recolectar PDFs agrupados por OC: OC → Facturas → Entregas
        $archivosAdjuntos = [];
        $facturasAgrupadas = $facturas->groupBy('orden_compra_id');

        foreach ($facturasAgrupadas as $facturasDeOc) {
            $oc = $facturasDeOc->first()->ordenCompra;

            // PDF de la orden de compra (firmado > formato > archivo)
            if ($oc) {
                $ocPdf = $oc->pdfFirmado ?? $oc->pdfFormato ?? $oc->archivo;
                if ($ocPdf && Storage::disk('public')->exists($ocPdf->path)) {
                    $archivosAdjuntos[] = Storage::disk('public')->path($ocPdf->path);
                }
            }

            // PDFs de facturas y sus entregas
            foreach ($facturasDeOc as $factura) {
                if ($factura->mediaPdf && Storage::disk('public')->exists($factura->mediaPdf->path)) {
                    $archivosAdjuntos[] = Storage::disk('public')->path($factura->mediaPdf->path);
                }
                foreach ($factura->entregas as $entrega) {
                    if ($entrega->media && $entrega->media->mime === 'application/pdf' && Storage::disk('public')->exists($entrega->media->path)) {
                        $archivosAdjuntos[] = Storage::disk('public')->path($entrega->media->path);
                    }
                }
            }
        }

        // Si no hay adjuntos, devolver solo el resumen
        if (empty($archivosAdjuntos)) {
            $filename = "reporte-semanal-facturas-S{$semana}-{$anio}.pdf";

            return $resumenPdf->download($filename);
        }

        // Mergear con FPDI: resumen + adjuntos
        $tempResumen = tempnam(sys_get_temp_dir(), 'resumen_').'.pdf';
        file_put_contents($tempResumen, $resumenPdf->output());

        $merger = new Fpdi;

        // Agregar páginas del resumen
        $this->agregarPaginasFpdi($merger, $tempResumen);

        // Agregar cada adjunto
        foreach ($archivosAdjuntos as $archivoPath) {
            try {
                $this->agregarPaginasFpdi($merger, $archivoPath);
            } catch (\Exception $e) {
                // Si un PDF no se puede leer, continuar con los demás
                continue;
            }
        }

        $filename = "reporte-semanal-facturas-S{$semana}-{$anio}.pdf";
        $outputPath = tempnam(sys_get_temp_dir(), 'merged_').'.pdf';
        $merger->Output($outputPath, 'F');

        // Limpiar temporal del resumen
        @unlink($tempResumen);

        return response()->download($outputPath, $filename)->deleteFileAfterSend(true);
    }

    public function reporteSemanalProveedor(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $request->validate([
            'anio' => 'required|integer|min:2020|max:2100',
            'semana' => 'required|integer|min:1|max:53',
            'proveedor_id' => 'required|exists:proveedores,id',
        ]);

        $anio = (int) $request->anio;
        $semana = (int) $request->semana;
        $proveedor = Proveedor::findOrFail($request->proveedor_id);

        $inicioSemana = Carbon::now()->setISODate($anio, $semana)->startOfWeek();
        $finSemana = (clone $inicioSemana)->endOfWeek();

        $facturas = Factura::query()
            ->with(['proveedor:id,razon_social', 'ordenCompra:id,folio', 'ordenCompra.pdfFirmado', 'ordenCompra.pdfFormato', 'ordenCompra.archivo', 'mediaPdf', 'entregas.media'])
            ->where('proveedor_id', $proveedor->id)
            ->whereHas('entregas', fn ($q) => $q->whereBetween('fecha_entrega', [$inicioSemana->toDateString(), $finSemana->toDateString()]))
            ->orderBy('orden_compra_id')
            ->orderBy('fecha_factura')
            ->get();

        if ($facturas->isEmpty()) {
            return back()->withErrors(['semana' => 'No se encontraron facturas del proveedor en la semana seleccionada.']);
        }

        $resumenPdf = Pdf::loadView('pdf.costos.reporte-semanal-facturas-proveedor', [
            'facturas' => $facturas,
            'proveedor' => $proveedor,
            'semana' => $semana,
            'anio' => $anio,
            'fechaInicio' => $inicioSemana->format('d/m/Y'),
            'fechaFin' => $finSemana->format('d/m/Y'),
        ])->setPaper('letter', 'landscape')
            ->setOption('margin-top', 30)
            ->setOption('margin-bottom', 60)
            ->setOption('margin-left', 60)
            ->setOption('margin-right', 60);

        // Recolectar PDFs agrupados por OC: OC → Facturas → Entregas
        $archivosAdjuntos = [];
        $facturasAgrupadas = $facturas->groupBy('orden_compra_id');

        foreach ($facturasAgrupadas as $facturasDeOc) {
            $oc = $facturasDeOc->first()->ordenCompra;

            if ($oc) {
                $ocPdf = $oc->pdfFirmado ?? $oc->pdfFormato ?? $oc->archivo;
                if ($ocPdf && Storage::disk('public')->exists($ocPdf->path)) {
                    $archivosAdjuntos[] = Storage::disk('public')->path($ocPdf->path);
                }
            }

            foreach ($facturasDeOc as $factura) {
                if ($factura->mediaPdf && Storage::disk('public')->exists($factura->mediaPdf->path)) {
                    $archivosAdjuntos[] = Storage::disk('public')->path($factura->mediaPdf->path);
                }
                foreach ($factura->entregas as $entrega) {
                    if ($entrega->media && $entrega->media->mime === 'application/pdf' && Storage::disk('public')->exists($entrega->media->path)) {
                        $archivosAdjuntos[] = Storage::disk('public')->path($entrega->media->path);
                    }
                }
            }
        }

        $filename = "reporte-semanal-{$proveedor->id}-S{$semana}-{$anio}.pdf";

        if (empty($archivosAdjuntos)) {
            return $resumenPdf->download($filename);
        }

        $tempResumen = tempnam(sys_get_temp_dir(), 'resumen_').'.pdf';
        file_put_contents($tempResumen, $resumenPdf->output());

        $merger = new Fpdi;
        $this->agregarPaginasFpdi($merger, $tempResumen);

        foreach ($archivosAdjuntos as $archivoPath) {
            try {
                $this->agregarPaginasFpdi($merger, $archivoPath);
            } catch (\Exception $e) {
                continue;
            }
        }

        $outputPath = tempnam(sys_get_temp_dir(), 'merged_').'.pdf';
        $merger->Output($outputPath, 'F');
        @unlink($tempResumen);

        return response()->download($outputPath, $filename)->deleteFileAfterSend(true);
    }

    private function agregarPaginasFpdi(Fpdi $fpdi, string $filePath): void
    {
        $pageCount = $fpdi->setSourceFile($filePath);
        for ($i = 1; $i <= $pageCount; $i++) {
            $templateId = $fpdi->importPage($i);
            $size = $fpdi->getTemplateSize($templateId);
            $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $fpdi->useTemplate($templateId);
        }
    }
}
