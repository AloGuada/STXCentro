<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\CancelarRequest;
use App\Http\Requests\Admin\Costos\SolicitudArchivoStoreRequest;
use App\Http\Requests\Admin\Costos\SolicitudFirmadoRequest;
use App\Http\Requests\Admin\Costos\SolicitudPagoStoreRequest;
use App\Http\Requests\Admin\Costos\SolicitudPagoUpdateRequest;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Pago;
use App\Models\Costos\SolicitudArchivo;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\Costos\TipoSolicitud;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Services\Costos\ApartadoPresupuestal;
use App\Services\Costos\ApprovalChainService;
use App\Services\Costos\FirmasPdfBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SolicitudPagoController extends Controller
{
    public function __construct(private readonly ApartadoPresupuestal $apartado) {}

    public function index(Request $request): Response
    {
        Gate::authorize('costos.solicitudes-pago.ver');

        $solicitudes = SolicitudPago::query()
            // Los usuarios comunes solo ven sus solicitudes; los operadores con
            // `ver-todas` ven las de todos.
            ->unless($request->user()->can('costos.solicitudes-pago.ver-todas'), fn ($q) => $q->where('solicitante_id', $request->user()->id))
            ->with(['departamento', 'proveedor', 'solicitante', 'media'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('folio', 'like', "%{$search}%")
                        ->orWhere('concepto', 'like', "%{$search}%");
                });
            })
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/costos/solicitudes-pago/index', [
            'solicitudes' => $solicitudes,
            'filters' => $request->only('search', 'estatus'),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('costos.solicitudes-pago.crear');

        return Inertia::render('admin/costos/solicitudes-pago/create', [
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'proveedores' => Proveedor::where('activo', true)
                ->with(['complementosPago' => fn ($q) => $q->whereIn('estatus', ['pendiente', 'vencido'])])
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nombre_comercial'])
                ->each->append('bloqueado_complemento'),
            'tipoSolicitudes' => TipoSolicitud::with('documentos')->orderBy('titulo')->get(),
            'obras' => Obra::orderBy('no')->get(['id', 'no', 'descripcion']),
            'obraRubros' => ObraRubro::with([
                'rubro',
                'obra:id,estatus',
            ])->get(),
        ]);
    }

    public function store(SolicitudPagoStoreRequest $request): RedirectResponse
    {
        Gate::authorize('costos.solicitudes-pago.crear');

        if ($proveedorId = $request->integer('proveedor_id')) {
            $proveedor = Proveedor::find($proveedorId);
            if ($proveedor?->bloqueadoPorComplemento()) {
                return back()
                    ->withInput()
                    ->withErrors(['proveedor_id' => 'Proveedor bloqueado por complemento de pago pendiente. No puede generar solicitudes de pago hasta regularizar.']);
            }
        }

        $warnings = [];
        $solicitud = null;

        DB::transaction(function () use ($request, &$warnings, &$solicitud) {
            $solicitud = SolicitudPago::create([
                ...$request->safe()->except(['detalles', 'archivos']),
                'solicitante_id' => $request->user()->id,
                'estatus' => 'pendiente_firma',
            ]);

            $montoTotal = 0;

            foreach ($request->input('detalles', []) as $detalle) {
                $subtotal = round((float) $detalle['cantidad'] * (float) $detalle['precio_unitario'], 2);

                $obraRubro = ObraRubro::with(['rubro:id,codigo', 'obra:id,estatus'])
                    ->find($detalle['obra_rubro_id']);

                $solicitud->detalles()->create([
                    'obra_rubro_id' => $detalle['obra_rubro_id'],
                    'sobre_obra_cerrada' => $obraRubro?->estaCerrado() ?? false,
                    'concepto' => $detalle['concepto'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'subtotal' => $subtotal,
                ]);
                $montoTotal += $subtotal;

                // Check budget
                if ($obraRubro) {
                    $disponible = $obraRubro->disponible;
                    if ($subtotal > $disponible) {
                        $warnings[] = "El centro de costos {$obraRubro->rubro?->codigo} excede el presupuesto disponible.";
                    }
                }
            }

            // Process uploaded files
            $uploadedFiles = $request->file('archivos', []);
            if (! empty($uploadedFiles)) {
                $textos = $request->input('archivos_texto', []);
                foreach ($uploadedFiles as $documentoId => $files) {
                    $fileList = is_array($files) ? $files : [$files];
                    foreach ($fileList as $index => $file) {
                        $path = $file->store("costos/solicitudes/{$solicitud->id}", 'public');
                        $media = \App\Models\Media::create([
                            'descripcion' => DocumentoTipo::SolicitudArchivo->value,
                            'nombre_original' => $file->getClientOriginalName(),
                            'path' => $path,
                            'mime' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ]);
                        $solicitud->archivos()->create([
                            'media_id' => $media->id,
                            'archivo_id' => $documentoId,
                            'texto_adicional' => $textos[$documentoId][$index] ?? null,
                        ]);
                    }
                }
            }

            $solicitud->update(['monto_total' => $montoTotal]);

            // Apartado temporal de presupuesto (5 días) en cada rubro de la
            // solicitud. Si no se aprueba en ese plazo, el comando programado
            // costos:liberar-apartados-vencidos lo libera automáticamente.
            $items = collect($solicitud->detalles)->map(fn ($d) => [
                'obra_rubro_id' => (int) $d->obra_rubro_id,
                'monto' => (float) $d->subtotal,
                'descripcion' => $d->concepto,
            ]);
            $this->apartado->apartarDocumento($solicitud, $items, $request->user()->id);

            app(ApprovalChainService::class)->crearCadenaAprobaciones($solicitud);
        });

        $redirect = to_route('admin.costos.solicitudes-pago.show', $solicitud);

        if (count($warnings) > 0) {
            $redirect->with('warning', implode(' ', $warnings));
        }

        return $redirect;
    }

    /**
     * Re-aparta el presupuesto de una solicitud cuyos apartados vencieron.
     * Crea nuevos RubroAfectado(Apartado) con apartado_hasta = hoy + 5 días.
     * Si la solicitud todavía tiene apartados vigentes, no hace nada.
     */
    public function reApartar(Request $request, SolicitudPago $solicitudPago): RedirectResponse
    {
        Gate::authorize('costos.solicitudes-pago.editar');

        abort_unless($solicitudPago->solicitante_id === $request->user()->id, 403);

        if (! in_array($solicitudPago->estatus->value, ['pendiente_firma', 'borrador'], true)) {
            return back()->withErrors(['estatus' => 'Solo se puede re-apartar una solicitud que aún no está aprobada o pagada.']);
        }

        $items = $solicitudPago->detalles->map(fn ($d) => [
            'obra_rubro_id' => (int) $d->obra_rubro_id,
            'monto' => (float) $d->subtotal,
            'descripcion' => $d->concepto,
        ]);

        $this->apartado->reApartarDocumento($solicitudPago, $items, $request->user()->id);

        return back()->with('success', 'Presupuesto re-apartado por 5 días.');
    }

    public function show(SolicitudPago $solicitudPago): Response
    {
        Gate::authorize('costos.solicitudes-pago.ver');

        $solicitudPago->load([
            'solicitante',
            'departamento',
            'proveedor',
            'tipoSolicitud.documentos',
            'detalles.obraRubro.rubro',
            'archivos.documento',
            'archivos.media',
            'aprobaciones.aprobador',
            'pago',
            'confirmadorCostos',
            'confirmadorContabilidad',
            'ordenCompra.requisicion:id,folio',
            'activities.causer',
        ]);

        return Inertia::render('admin/costos/solicitudes-pago/show', [
            'solicitud' => $solicitudPago,
            'documentosPrevios' => $this->documentosPrevios($solicitudPago),
        ]);
    }

    /**
     * PDFs de los documentos que originaron la solicitud (OC de contado y su
     * requisición), para mostrarlos en el tab de Documentos.
     *
     * @return list<array{label: string, url: string}>
     */
    private function documentosPrevios(SolicitudPago $solicitudPago): array
    {
        $oc = $solicitudPago->ordenCompra;

        if (! $oc) {
            return [];
        }

        $documentos = [[
            'label' => "Orden de Compra {$oc->folio}",
            'url' => route('admin.costos.ordenes-compra.pdf-oc', $oc),
        ]];

        if ($oc->requisicion) {
            $documentos[] = [
                'label' => "Requisición {$oc->requisicion->folio} (comparativo)",
                'url' => route('admin.costos.ordenes-compra.pdf-requisicion', $oc),
            ];
        }

        return $documentos;
    }

    public function edit(SolicitudPago $solicitudPago): Response|RedirectResponse
    {
        Gate::authorize('costos.solicitudes-pago.editar');

        if ($solicitudPago->estatus !== SolicitudPagoEstatus::Borrador) {
            return to_route('admin.costos.solicitudes-pago.show', $solicitudPago);
        }

        $solicitudPago->load(['detalles.obraRubro.rubro', 'tipoSolicitud.documentos', 'archivos.documento', 'lockedBy:id,name']);

        return Inertia::render('admin/costos/solicitudes-pago/edit', [
            'solicitud' => $solicitudPago,
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'proveedores' => Proveedor::where('activo', true)
                ->with(['complementosPago' => fn ($q) => $q->whereIn('estatus', ['pendiente', 'vencido'])])
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nombre_comercial'])
                ->each->append('bloqueado_complemento'),
            'tipoSolicitudes' => TipoSolicitud::with('documentos')->orderBy('titulo')->get(),
            'obras' => Obra::orderBy('no')->get(['id', 'no', 'descripcion']),
            'obraRubros' => ObraRubro::with([
                'rubro',
                'obra:id,estatus',
            ])->get(),
        ]);
    }

    public function update(SolicitudPagoUpdateRequest $request, SolicitudPago $solicitudPago): RedirectResponse
    {
        Gate::authorize('costos.solicitudes-pago.editar');

        if ($solicitudPago->estatus !== SolicitudPagoEstatus::Borrador) {
            return back()->withErrors(['estatus' => 'Solo se pueden editar solicitudes en borrador.']);
        }

        $solicitudPago->assertVersion($request->input('_version'));

        $warnings = [];

        DB::transaction(function () use ($request, $solicitudPago, &$warnings) {
            $solicitudPago->update($request->safe()->except('detalles'));

            // Sync detalles (same pattern as TipoSolicitudController)
            $incomingIds = collect($request->input('detalles', []))
                ->pluck('id')
                ->filter()
                ->all();

            $solicitudPago->detalles()
                ->whereNotIn('id', $incomingIds)
                ->delete();

            $montoTotal = 0;

            foreach ($request->input('detalles', []) as $detalle) {
                $subtotal = round((float) $detalle['cantidad'] * (float) $detalle['precio_unitario'], 2);

                $obraRubro = ObraRubro::with(['rubro:id,codigo', 'obra:id,estatus'])
                    ->find($detalle['obra_rubro_id']);
                $sobreObraCerrada = $obraRubro?->estaCerrado() ?? false;

                if (! empty($detalle['id'])) {
                    SolicitudPagoDetalle::where('id', $detalle['id'])->update([
                        'obra_rubro_id' => $detalle['obra_rubro_id'],
                        'sobre_obra_cerrada' => $sobreObraCerrada,
                        'concepto' => $detalle['concepto'],
                        'cantidad' => $detalle['cantidad'],
                        'precio_unitario' => $detalle['precio_unitario'],
                        'subtotal' => $subtotal,
                    ]);
                } else {
                    $solicitudPago->detalles()->create([
                        'obra_rubro_id' => $detalle['obra_rubro_id'],
                        'sobre_obra_cerrada' => $sobreObraCerrada,
                        'concepto' => $detalle['concepto'],
                        'cantidad' => $detalle['cantidad'],
                        'precio_unitario' => $detalle['precio_unitario'],
                        'subtotal' => $subtotal,
                    ]);
                }

                $montoTotal += $subtotal;

                // Check budget
                if ($obraRubro) {
                    $disponible = $obraRubro->disponible;
                    if ($subtotal > $disponible) {
                        $warnings[] = "El centro de costos {$obraRubro->rubro?->codigo} excede el presupuesto disponible.";
                    }
                }
            }

            $solicitudPago->update(['monto_total' => $montoTotal]);
            $solicitudPago->unlock();
        });

        $redirect = to_route('admin.costos.solicitudes-pago.index');

        if (count($warnings) > 0) {
            $redirect->with('warning', implode(' ', $warnings));
        }

        return $redirect;
    }

    public function destroy(SolicitudPago $solicitudPago): RedirectResponse
    {
        Gate::authorize('costos.solicitudes-pago.eliminar');

        if ($solicitudPago->estatus !== SolicitudPagoEstatus::Borrador) {
            return back()->withErrors(['estatus' => 'Solo se pueden eliminar solicitudes en borrador.']);
        }

        $solicitudPago->delete();

        return to_route('admin.costos.solicitudes-pago.index');
    }

    public function storeArchivo(SolicitudArchivoStoreRequest $request, SolicitudPago $solicitudPago): RedirectResponse
    {
        Gate::authorize('costos.solicitudes-pago.editar');

        $file = $request->file('archivo');
        $path = $file->store("costos/solicitudes/{$solicitudPago->id}", 'public');

        $media = \App\Models\Media::create([
            'descripcion' => DocumentoTipo::SolicitudArchivo->value,
            'nombre_original' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        $solicitudPago->archivos()->create([
            'media_id' => $media->id,
            'archivo_id' => $request->input('archivo_id'),
            'texto_adicional' => $request->input('texto_adicional'),
        ]);

        return back()->with('success', 'Archivo subido correctamente.');
    }

    public function updateArchivo(Request $request, SolicitudPago $solicitudPago, SolicitudArchivo $solicitudArchivo): RedirectResponse
    {
        Gate::authorize('costos.solicitudes-pago.editar');

        if ($solicitudArchivo->solicitud_id !== $solicitudPago->id) {
            abort(404);
        }

        $request->validate([
            'texto_adicional' => ['nullable', 'string', 'max:255'],
        ]);

        $solicitudArchivo->update([
            'texto_adicional' => $request->input('texto_adicional'),
        ]);

        return back()->with('success', 'Archivo actualizado correctamente.');
    }

    public function destroyArchivo(SolicitudPago $solicitudPago, SolicitudArchivo $solicitudArchivo): RedirectResponse
    {
        Gate::authorize('costos.solicitudes-pago.editar');

        if ($solicitudArchivo->solicitud_id !== $solicitudPago->id) {
            abort(404);
        }

        $media = $solicitudArchivo->media;
        $solicitudArchivo->delete();

        if ($media) {
            Storage::disk('public')->delete($media->path);
            $media->delete();
        }

        return back()->with('success', 'Archivo eliminado correctamente.');
    }

    public function generarPdf(SolicitudPago $solicitudPago): HttpResponse
    {
        Gate::authorize('costos.solicitudes-pago.ver');

        $solicitudPago->load([
            'solicitante',
            'departamento',
            'proveedor',
            'tipoSolicitud',
            'detalles.obraRubro.rubro',
        ]);

        // Cambiar estatus a pendiente_firma
        if ($solicitudPago->estatus === SolicitudPagoEstatus::Borrador) {
            $solicitudPago->transitionTo(SolicitudPagoEstatus::PendienteFirma);
            app(ApprovalChainService::class)->crearCadenaAprobaciones($solicitudPago);
        }

        // Columnas de firma: solo los niveles que aplican a este tipo de
        // documento y al departamento de la solicitud.
        $solicitudPago->load('aprobaciones.aprobador');
        $firmasPdf = app(FirmasPdfBuilder::class)->build(
            $solicitudPago->tipoAprobacion(),
            $solicitudPago->departamento_id,
            $solicitudPago->aprobaciones,
        );

        $pdf = Pdf::loadView('pdf.costos.formato-solicitud-pago', [
            'solicitud' => $solicitudPago,
            'firmasPdf' => $firmasPdf,
        ])->setPaper('letter', 'portrait')
            ->setOption('margin-top', 30)
            ->setOption('margin-bottom', 60)
            ->setOption('margin-left', 60)
            ->setOption('margin-right', 60);

        $filename = "solicitud-pago-{$solicitudPago->folio}.pdf";

        return $pdf->download($filename);
    }

    public function uploadFirmado(SolicitudFirmadoRequest $request, SolicitudPago $solicitudPago): RedirectResponse
    {
        Gate::authorize('costos.solicitudes-pago.editar');

        if ($solicitudPago->estatus !== SolicitudPagoEstatus::PendienteFirma) {
            return back()->withErrors(['estatus' => 'La solicitud debe estar en pendiente de firma.']);
        }

        $file = $request->file('archivo');
        $solicitudPago->media()->create([
            'descripcion' => DocumentoTipo::SolicitudFirmada->value,
            'nombre_original' => $file->getClientOriginalName(),
            'path' => $file->store('costos/firmados', 'public'),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
        $solicitudPago->transitionTo(SolicitudPagoEstatus::Aprobada);

        // Marcar aprobaciones
        $solicitudPago->aprobaciones()->update([
            'estatus' => 'aprobada',
            'fecha_respuesta' => now(),
        ]);

        // Aplicar impacto presupuestal
        $solicitudPago->aplicarImpactoPresupuestal($request->user()->id);

        return back()->with('success', 'Solicitud aprobada correctamente.');
    }

    public function cancelar(CancelarRequest $request, SolicitudPago $solicitudPago): RedirectResponse
    {
        Gate::authorize('costos.solicitudes-pago.editar');

        if (! in_array($solicitudPago->estatus, [SolicitudPagoEstatus::PendienteFirma, SolicitudPagoEstatus::Aprobada], true)) {
            return back()->withErrors(['estatus' => 'Solo se pueden cancelar solicitudes pendientes o aprobadas.']);
        }

        // Revertir impacto si estaba aprobada
        if ($solicitudPago->estatus === SolicitudPagoEstatus::Aprobada) {
            foreach ($solicitudPago->detalles as $detalle) {
                ObraRubro::where('id', $detalle->obra_rubro_id)
                    ->decrement('acumulado', (float) $detalle->subtotal);
            }

            $solicitudPago->rubrosAfectados()->create([
                'obra_rubro_id' => $solicitudPago->detalles->first()?->obra_rubro_id ?? 0,
                'monto' => $solicitudPago->monto_total,
                'descripcion' => 'Cancelación de solicitud',
                'tipo_movimiento' => 'abono',
                'estatus' => 'cancelado',
                'usuario_aplica_id' => auth()->id(),
                'fecha_aplicacion' => now(),
            ]);
        }

        $solicitudPago->transitionTo(SolicitudPagoEstatus::Cancelada);
        $solicitudPago->registrarCancelacion($request->validated('motivo'), $request->user()->id);

        return back()->with('success', 'Solicitud cancelada.');
    }

    public function confirmarCostos(Request $request, SolicitudPago $solicitudPago): RedirectResponse
    {
        Gate::authorize('costos.solicitudes.confirmar-costos');

        if ($solicitudPago->estatus !== SolicitudPagoEstatus::Aprobada) {
            return back()->withErrors(['estatus' => 'La solicitud debe estar aprobada.']);
        }

        if ($solicitudPago->confirmada_costos) {
            return back()->withErrors(['confirmada_costos' => 'La solicitud ya fue confirmada por costos.']);
        }

        $solicitudPago->update([
            'confirmada_costos' => true,
            'confirmada_costos_por' => $request->user()->id,
            'confirmada_costos_at' => now(),
        ]);

        // Contado: crear pago inmediatamente
        if ($solicitudPago->tipo_pago !== 'credito') {
            Pago::create([
                'pagable_type' => SolicitudPago::class,
                'pagable_id' => $solicitudPago->id,
                'monto_pago' => $solicitudPago->monto_total,
                'moneda' => $solicitudPago->tipo_moneda ?? 'mxn',
                'tipo_pago' => 'contado',
                'fecha_pago_programada' => $solicitudPago->fecha_pago_solicitada,
                'estatus' => 'programado',
            ]);

            return back()->with('success', 'Solicitud confirmada y pago programado.');
        }

        return back()->with('success', 'Solicitud confirmada por costos. Pendiente confirmación de contabilidad.');
    }

    public function confirmarContabilidad(Request $request, SolicitudPago $solicitudPago): RedirectResponse
    {
        Gate::authorize('costos.facturas.aceptar-contabilidad');

        if ($solicitudPago->estatus !== SolicitudPagoEstatus::Aprobada) {
            return back()->withErrors(['estatus' => 'La solicitud debe estar aprobada.']);
        }

        if (! $solicitudPago->confirmada_costos) {
            return back()->withErrors(['confirmada_costos' => 'La solicitud debe ser confirmada por costos primero.']);
        }

        if ($solicitudPago->confirmada_contabilidad) {
            return back()->withErrors(['confirmada_contabilidad' => 'La solicitud ya fue confirmada por contabilidad.']);
        }

        $solicitudPago->update([
            'confirmada_contabilidad' => true,
            'confirmada_contabilidad_por' => $request->user()->id,
            'confirmada_contabilidad_at' => now(),
        ]);

        Pago::create([
            'pagable_type' => SolicitudPago::class,
            'pagable_id' => $solicitudPago->id,
            'monto_pago' => $solicitudPago->monto_total,
            'moneda' => $solicitudPago->tipo_moneda ?? 'mxn',
            'tipo_pago' => 'credito',
            'fecha_pago_programada' => $solicitudPago->fecha_pago_solicitada,
            'estatus' => 'programado',
        ]);

        return back()->with('success', 'Solicitud confirmada por contabilidad y pago programado.');
    }
}
