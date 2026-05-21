<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\CancelarRequest;
use App\Http\Requests\Admin\Costos\SolicitudPagoStoreRequest;
use App\Http\Requests\Admin\Costos\SolicitudPagoUpdateRequest;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Pago;
use App\Models\Costos\Permiso;
use App\Models\Costos\SolicitudArchivo;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\Costos\TipoSolicitud;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Services\Costos\ApartadoPresupuestal;
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
        $solicitudes = SolicitudPago::query()
            ->where('solicitante_id', auth()->id())
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
        return Inertia::render('admin/costos/solicitudes-pago/create', [
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'proveedores' => Proveedor::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
            'tipoSolicitudes' => TipoSolicitud::with('documentos')->orderBy('titulo')->get(),
            'obras' => Obra::orderBy('no')->get(['id', 'no', 'descripcion']),
            'obraRubros' => ObraRubro::with('rubro')->get(),
        ]);
    }

    public function store(SolicitudPagoStoreRequest $request): RedirectResponse
    {
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
                $solicitud->detalles()->create([
                    'obra_rubro_id' => $detalle['obra_rubro_id'],
                    'concepto' => $detalle['concepto'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'subtotal' => $subtotal,
                ]);
                $montoTotal += $subtotal;

                // Check budget
                $obraRubro = ObraRubro::find($detalle['obra_rubro_id']);
                if ($obraRubro) {
                    $disponible = (float) $obraRubro->presupuestado - (float) $obraRubro->acumulado;
                    if ($subtotal > $disponible) {
                        $warnings[] = "El rubro {$obraRubro->rubro?->codigo} excede el presupuesto disponible.";
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

            // Crear cadena de aprobaciones del departamento (multiusuario por nivel)
            $cadenaAprobacion = AprobacionDepartamento::where('departamento_id', $solicitud->departamento_id)
                ->with('permiso')
                ->get()
                ->sortBy('permiso.nivel')
                ->values();

            foreach ($cadenaAprobacion as $asignacion) {
                $solicitud->aprobaciones()->create([
                    'nivel' => $asignacion->permiso->nivel,
                    'aprobador_id' => $asignacion->aprobador_id,
                    'estatus' => 'pendiente',
                ]);
            }
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
            'activities.causer',
        ]);

        return Inertia::render('admin/costos/solicitudes-pago/show', [
            'solicitud' => $solicitudPago,
        ]);
    }

    public function edit(SolicitudPago $solicitudPago): Response|RedirectResponse
    {
        if ($solicitudPago->estatus !== SolicitudPagoEstatus::Borrador) {
            return to_route('admin.costos.solicitudes-pago.show', $solicitudPago);
        }

        $solicitudPago->load(['detalles.obraRubro.rubro', 'tipoSolicitud.documentos', 'archivos.documento', 'lockedBy:id,name']);

        return Inertia::render('admin/costos/solicitudes-pago/edit', [
            'solicitud' => $solicitudPago,
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'proveedores' => Proveedor::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
            'tipoSolicitudes' => TipoSolicitud::with('documentos')->orderBy('titulo')->get(),
            'obras' => Obra::orderBy('no')->get(['id', 'no', 'descripcion']),
            'obraRubros' => ObraRubro::with('rubro')->get(),
        ]);
    }

    public function update(SolicitudPagoUpdateRequest $request, SolicitudPago $solicitudPago): RedirectResponse
    {
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

                if (! empty($detalle['id'])) {
                    SolicitudPagoDetalle::where('id', $detalle['id'])->update([
                        'obra_rubro_id' => $detalle['obra_rubro_id'],
                        'concepto' => $detalle['concepto'],
                        'cantidad' => $detalle['cantidad'],
                        'precio_unitario' => $detalle['precio_unitario'],
                        'subtotal' => $subtotal,
                    ]);
                } else {
                    $solicitudPago->detalles()->create([
                        'obra_rubro_id' => $detalle['obra_rubro_id'],
                        'concepto' => $detalle['concepto'],
                        'cantidad' => $detalle['cantidad'],
                        'precio_unitario' => $detalle['precio_unitario'],
                        'subtotal' => $subtotal,
                    ]);
                }

                $montoTotal += $subtotal;

                // Check budget
                $obraRubro = ObraRubro::find($detalle['obra_rubro_id']);
                if ($obraRubro) {
                    $disponible = (float) $obraRubro->presupuestado - (float) $obraRubro->acumulado;
                    if ($subtotal > $disponible) {
                        $warnings[] = "El rubro {$obraRubro->rubro?->codigo} excede el presupuesto disponible.";
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
        if ($solicitudPago->estatus !== SolicitudPagoEstatus::Borrador) {
            return back()->withErrors(['estatus' => 'Solo se pueden eliminar solicitudes en borrador.']);
        }

        $solicitudPago->delete();

        return to_route('admin.costos.solicitudes-pago.index');
    }

    public function storeArchivo(Request $request, SolicitudPago $solicitudPago): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:10240'],
            'archivo_id' => ['required', 'exists:costos_documentos,id'],
            'texto_adicional' => ['nullable', 'string', 'max:255'],
        ]);

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
        $solicitudPago->load([
            'solicitante',
            'departamento',
            'proveedor',
            'tipoSolicitud',
            'detalles.obraRubro.rubro',
        ]);

        $cadenaAprobacion = AprobacionDepartamento::where('departamento_id', $solicitudPago->departamento_id)
            ->with(['permiso', 'aprobador'])
            ->get()
            ->sortBy('permiso.nivel')
            ->values();

        // Cambiar estatus a pendiente_firma
        if ($solicitudPago->estatus === SolicitudPagoEstatus::Borrador) {
            $solicitudPago->transitionTo(SolicitudPagoEstatus::PendienteFirma);

            // Crear registros de aprobación (uno por aprobador por nivel)
            foreach ($cadenaAprobacion as $asignacion) {
                $solicitudPago->aprobaciones()->create([
                    'nivel' => $asignacion->permiso->nivel,
                    'aprobador_id' => $asignacion->aprobador_id,
                    'estatus' => 'pendiente',
                ]);
            }
        }

        // Agrupar aprobaciones por nivel para el PDF (una columna por nivel)
        $solicitudPago->load('aprobaciones.aprobador');
        $niveles = Permiso::orderBy('nivel')->get();
        $aprobacionesPorNivel = $solicitudPago->aprobaciones->groupBy('nivel');

        $firmasPdf = $niveles->filter(fn ($permiso) => $aprobacionesPorNivel->has($permiso->nivel))
            ->map(function ($permiso) use ($aprobacionesPorNivel) {
                $aprobaciones = $aprobacionesPorNivel->get($permiso->nivel);
                $aprobada = $aprobaciones->firstWhere('estatus', 'aprobada');

                return (object) [
                    'permiso' => $permiso,
                    'aprobador' => $aprobada?->aprobador,
                    'aprobada' => $aprobada !== null,
                ];
            })
            ->values();

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

    public function uploadFirmado(Request $request, SolicitudPago $solicitudPago): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

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
        $solicitudPago->update([
            'estatus' => 'aprobada',
        ]);

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
