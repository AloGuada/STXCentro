<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\AprobacionEstatus;
use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\CancelarRequest;
use App\Http\Requests\Admin\Costos\ReasignarCentroCostosRequest;
use App\Http\Requests\Admin\Costos\SolicitudArchivoStoreRequest;
use App\Http\Requests\Admin\Costos\SolicitudFirmadoRequest;
use App\Http\Requests\Admin\Costos\SolicitudPagoStoreRequest;
use App\Http\Requests\Admin\Costos\SolicitudPagoUpdateRequest;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\ConfiguracionCostos;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Pago;
use App\Models\Costos\Requisicion;
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
use App\Services\Costos\ReasignacionCentroCostos;
use App\Support\OrdenaColumnas;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
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
    use OrdenaColumnas;

    public function __construct(private readonly ApartadoPresupuestal $apartado) {}

    /**
     * Reporte PDF con dos tablas: solicitudes de pago y requisiciones. Respeta
     * la misma visibilidad del index (solo propias salvo permiso `ver-todas`) y
     * el filtro de búsqueda por folio.
     */
    public function reportePdf(Request $request): HttpResponse
    {
        Gate::authorize('costos.solicitudes-pago.ver');

        $verTodas = $request->user()->can('costos.solicitudes-pago.ver-todas');
        $search = $request->string('search')->toString();

        $solicitudes = SolicitudPago::query()
            ->unless($verTodas, fn ($q) => $q->where('solicitante_id', $request->user()->id))
            ->with(['departamento', 'proveedor', 'solicitante'])
            ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('folio', 'like', "%{$s}%")
                ->orWhere('concepto', 'like', "%{$s}%")
                ->orWhereHas('solicitante', fn ($u) => $u->where('name', 'like', "%{$s}%"))
                ->orWhereHas('proveedor', fn ($p) => $p->where('razon_social', 'like', "%{$s}%")
                    ->orWhere('nombre_comercial', 'like', "%{$s}%"))))
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->latest()
            ->get();

        $requisiciones = Requisicion::query()
            ->unless($verTodas, fn ($q) => $q->where('solicitante_id', $request->user()->id))
            ->with(['departamento', 'solicitante'])
            ->when($search, fn ($q, $s) => $q->where('folio', 'like', "%{$s}%"))
            ->latest()
            ->get();

        $pdf = Pdf::loadView('pdf.costos.reporte-solicitudes-requisiciones', [
            'solicitudes' => $solicitudes,
            'requisiciones' => $requisiciones,
            'fechaGeneracion' => now(),
        ])->setPaper('letter', 'landscape');

        return $pdf->download('reporte-solicitudes-requisiciones-'.now()->format('Y-m-d').'.pdf');
    }

    public function index(Request $request): Response
    {
        Gate::authorize('costos.solicitudes-pago.ver');

        $query = SolicitudPago::query()
            // Los usuarios comunes solo ven sus solicitudes; los operadores con
            // `ver-todas` ven las de todos. Con `ver-departamentos-aprobador`, un
            // aprobador también ve las de los departamentos que aprueba. Con
            // `ver-departamento-propio`, un usuario ve lo creado por colegas de su
            // mismo departamento.
            ->unless($request->user()->can('costos.solicitudes-pago.ver-todas'), function ($q) use ($request) {
                $user = $request->user();
                $q->where(function ($sub) use ($user) {
                    $sub->where('solicitante_id', $user->id);

                    if ($user->can('costos.solicitudes-pago.ver-departamentos-aprobador')) {
                        $deptos = AprobacionDepartamento::departamentosDeAprobador($user->id, SolicitudPago::TIPO_APROBACION);
                        if ($deptos !== []) {
                            $sub->orWhereIn('departamento_id', $deptos);
                        }
                    }

                    if ($user->departamento_id && $user->can('costos.solicitudes-pago.ver-departamento-propio')) {
                        $sub->orWhereHas('solicitante', fn ($u) => $u->where('departamento_id', $user->departamento_id));
                    }
                });
            })
            ->with(['departamento', 'proveedor', 'solicitante', 'media'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('folio', 'like', "%{$search}%")
                        ->orWhere('concepto', 'like', "%{$search}%")
                        ->orWhereHas('solicitante', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('proveedor', fn ($p) => $p->where('razon_social', 'like', "%{$search}%")
                            ->orWhere('nombre_comercial', 'like', "%{$search}%"));
                });
            })
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e));

        $orden = $this->aplicarOrden($query, $request, [
            'folio' => 'folio',
            'concepto' => 'concepto',
            'monto_total' => 'monto_total',
            'estatus' => 'estatus',
            'fecha_pago_solicitada' => 'fecha_pago_solicitada',
            'solicitante' => fn (Builder $q, string $dir) => $q->orderBy(
                \App\Models\Usuario::select('name')->whereColumn('usuarios.id', 'costos_solicitudes_pago.solicitante_id'), $dir),
            'proveedor' => fn (Builder $q, string $dir) => $q->orderBy(
                Proveedor::select('razon_social')->whereColumn('proveedores.id', 'costos_solicitudes_pago.proveedor_id'), $dir),
        ], 'created_at', 'desc');

        $solicitudes = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/costos/solicitudes-pago/index', [
            'solicitudes' => $solicitudes,
            'filters' => $request->only('search', 'estatus'),
            'sortBy' => $orden['by'],
            'sortDir' => $orden['dir'],
        ]);
    }

    /**
     * Parámetros del corte semanal para el campo de fecha de pago solicitada,
     * consumidos por el formulario (mínimo viernes seleccionable y ayuda).
     *
     * @return array{activo: bool, dia: int, hora: string, min_viernes: string}
     */
    private function corteFechaPago(): array
    {
        $config = ConfiguracionCostos::actual();

        return [
            'activo' => $config->corte_activo,
            'dia' => $config->corte_dia,
            'hora' => $config->corte_hora,
            'min_viernes' => $config->minViernes()->toDateString(),
        ];
    }

    /**
     * Catálogo de centros de costos (obra-rubro) para los selectores de detalle
     * en create/edit y en el modal de reasignación del show.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ObraRubro>
     */
    private function obraRubrosParaSelector(): \Illuminate\Database\Eloquent\Collection
    {
        return ObraRubro::with([
            'rubro',
            'presupuesto:id,estatus',
        ])->get();
    }

    public function create(): Response
    {
        Gate::authorize('costos.solicitudes-pago.crear');

        return Inertia::render('admin/costos/solicitudes-pago/create', [
            'corteFechaPago' => $this->corteFechaPago(),
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'proveedores' => Proveedor::where('activo', true)
                ->with(['complementosPago' => fn ($q) => $q->whereIn('estatus', ['pendiente', 'vencido'])])
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nombre_comercial'])
                ->each->append('bloqueado_complemento'),
            'tipoSolicitudes' => TipoSolicitud::with('documentos')->orderBy('titulo')->get(),
            'obras' => Obra::orderBy('no')->get(['id', 'no', 'descripcion']),
            'obraRubros' => $this->obraRubrosParaSelector(),
            'usuarios' => \App\Models\Usuario::orderBy('name')->get(['id', 'name']),
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
                'estatus' => 'borrador',
            ]);

            $montoTotal = 0;

            $obraRubros = ObraRubro::with(['rubro:id,codigo', 'presupuesto:id,estatus'])
                ->findMany(collect($request->input('detalles', []))->pluck('obra_rubro_id')->filter()->unique())
                ->keyBy('id');

            foreach ($request->input('detalles', []) as $detalle) {
                $subtotal = round((float) $detalle['cantidad'] * (float) $detalle['precio_unitario'], 2);

                $obraRubro = $obraRubros->get((int) $detalle['obra_rubro_id']);

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

            // El total del pago es editable: si se captura, manda sobre la suma
            // de los detalles (que solo reparten el apartado por centro de costo).
            if ($request->filled('monto_total')) {
                $montoTotal = round((float) $request->input('monto_total'), 2);
            }

            $solicitud->update(['monto_total' => $montoTotal]);
        });

        $redirect = to_route('admin.costos.solicitudes-pago.show', $solicitud);

        if (count($warnings) > 0) {
            $redirect->with('warning', implode(' ', $warnings));
        }

        return $redirect;
    }

    /**
     * Envía una solicitud en borrador a la cadena de aprobación: transiciona a
     * pendiente_firma, aparta el presupuesto y crea la cadena de firmas. Solo el
     * creador de la solicitud puede enviarla.
     */
    public function enviarAprobacion(SolicitudPago $solicitudPago): RedirectResponse
    {
        abort_unless($solicitudPago->solicitante_id === auth()->id(), 403);

        if ($solicitudPago->estatus !== SolicitudPagoEstatus::Borrador) {
            return back()->withErrors(['estatus' => 'Solo se puede enviar a aprobación una solicitud en borrador.']);
        }

        DB::transaction(function () use ($solicitudPago) {
            $solicitudPago->loadMissing('detalles');
            $solicitudPago->transitionTo(SolicitudPagoEstatus::PendienteFirma);

            // Apartado temporal de presupuesto (5 días) por rubro. Antes vivía en
            // store(); se movió aquí para que el borrador no reserve presupuesto.
            $items = collect($solicitudPago->detalles)->map(fn ($d) => [
                'obra_rubro_id' => (int) $d->obra_rubro_id,
                'monto' => (float) $d->subtotal,
                'descripcion' => $d->concepto,
                'moneda' => $solicitudPago->tipo_moneda ?? 'mxn',
                'tipo_cambio' => $solicitudPago->tipo_cambio ? (float) $solicitudPago->tipo_cambio : null,
            ]);
            $this->apartado->apartarDocumento($solicitudPago, $items, auth()->id());

            app(ApprovalChainService::class)->crearCadenaAprobaciones($solicitudPago);
        });

        return to_route('admin.costos.solicitudes-pago.show', $solicitudPago)
            ->with('success', 'Solicitud enviada a aprobación.');
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
            'moneda' => $solicitudPago->tipo_moneda ?? 'mxn',
            'tipo_cambio' => $solicitudPago->tipo_cambio ? (float) $solicitudPago->tipo_cambio : null,
        ]);

        $this->apartado->reApartarDocumento($solicitudPago, $items, $request->user()->id);

        return back()->with('success', 'Presupuesto re-apartado por 5 días.');
    }

    /**
     * Reasigna los centros de costos de una solicitud ya aprobada/pagada sin
     * re-firmarla: revierte los cargos aplicados y aplica el nuevo set. Acción
     * privilegiada (permiso `costos.centros-costos.reasignar`, validado en el
     * Form Request) con motivo obligatorio y bitácora.
     */
    public function reasignar(
        ReasignarCentroCostosRequest $request,
        SolicitudPago $solicitudPago,
        ReasignacionCentroCostos $reasignacion,
    ): RedirectResponse {
        $reasignacion->reasignar(
            $solicitudPago,
            $request->validated('detalles'),
            $request->validated('motivo'),
            $request->user()->id,
        );

        return back()->with('success', 'Centros de costos reasignados.');
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
            'detalles.obraRubro.presupuesto.presupuestable',
            'archivos.documento',
            'archivos.media',
            'aprobaciones.aprobador',
            'pago',
            'confirmadorCostos',
            'confirmadorContabilidad',
            'ordenCompra.requisicion:id,folio,solicitante_id',
            'activities.causer',
        ]);

        // La columna de obra del detalle muestra la OP y el nombre (interno o de
        // cobranza) que resuelve el propio presupuesto.
        $solicitudPago->detalles->each(
            fn ($detalle) => $detalle->obraRubro?->presupuesto?->append(['op_mostrar', 'descripcion_mostrar']),
        );

        // Solo ve la solicitud quien puede ver todas, el solicitante, el
        // solicitante de la requisición de la OC que la originó (contado), o un
        // aprobador asignado en su cadena de firmas.
        $user = auth()->user();
        abort_unless(
            $user->can('costos.solicitudes-pago.ver-todas')
                || $solicitudPago->solicitante_id === $user->id
                || $solicitudPago->ordenCompra?->requisicion?->solicitante_id === $user->id
                || $solicitudPago->aprobaciones->contains('aprobador_id', $user->id),
            403,
        );

        $solicitudPago->append('puede_reasignar');

        // El catálogo de centros de costos solo se carga si la solicitud admite
        // reasignación y el usuario tiene el permiso privilegiado, para no inflar
        // el show del resto de solicitudes.
        $puedeReasignar = $solicitudPago->puede_reasignar
            && $user->can('costos.centros-costos.reasignar');

        return Inertia::render('admin/costos/solicitudes-pago/show', [
            'solicitud' => $solicitudPago,
            'documentosPrevios' => $this->documentosPrevios($solicitudPago),
            'obras' => $puedeReasignar
                ? Obra::orderBy('no')->get(['id', 'no', 'descripcion'])
                : [],
            'obraRubros' => $puedeReasignar ? $this->obraRubrosParaSelector() : [],
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

        $solicitudPago->load(['detalles.obraRubro.rubro', 'tipoSolicitud.documentos', 'archivos.documento', 'archivos.media', 'lockedBy:id,name']);

        return Inertia::render('admin/costos/solicitudes-pago/edit', [
            'solicitud' => $solicitudPago,
            'corteFechaPago' => $this->corteFechaPago(),
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'proveedores' => Proveedor::where('activo', true)
                ->with(['complementosPago' => fn ($q) => $q->whereIn('estatus', ['pendiente', 'vencido'])])
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nombre_comercial'])
                ->each->append('bloqueado_complemento'),
            'tipoSolicitudes' => TipoSolicitud::with('documentos')->orderBy('titulo')->get(),
            'obras' => Obra::orderBy('no')->get(['id', 'no', 'descripcion']),
            'obraRubros' => $this->obraRubrosParaSelector(),
            'usuarios' => \App\Models\Usuario::orderBy('name')->get(['id', 'name']),
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
            // `monto_total` se calcula del desglose (o del total capturado) y se
            // fija más abajo; no debe venir del request en el mass-assign porque en
            // solicitudes desglosadas llega null y viola el NOT NULL de la columna.
            $solicitudPago->update($request->safe()->except(['detalles', 'monto_total']));

            // Sync detalles (same pattern as TipoSolicitudController)
            $incomingIds = collect($request->input('detalles', []))
                ->pluck('id')
                ->filter()
                ->all();

            $solicitudPago->detalles()
                ->whereNotIn('id', $incomingIds)
                ->delete();

            $montoTotal = 0;

            $obraRubros = ObraRubro::with(['rubro:id,codigo', 'presupuesto:id,estatus'])
                ->findMany(collect($request->input('detalles', []))->pluck('obra_rubro_id')->filter()->unique())
                ->keyBy('id');

            foreach ($request->input('detalles', []) as $detalle) {
                $subtotal = round((float) $detalle['cantidad'] * (float) $detalle['precio_unitario'], 2);

                $obraRubro = $obraRubros->get((int) $detalle['obra_rubro_id']);
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

            // Obras sin desglose de rubros: el total se captura directo.
            if (empty($request->input('detalles', []))) {
                $montoTotal = round((float) $request->input('monto_total', 0), 2);
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

    public function generarPdf(Request $request, SolicitudPago $solicitudPago): HttpResponse
    {
        Gate::authorize('costos.solicitudes-pago.ver');

        $solicitudPago->load([
            'solicitante',
            'departamento',
            'proveedor',
            'tipoSolicitud',
            'detalles.obraRubro.rubro',
            'detalles.obraRubro.obra',
        ]);

        // El PDF en borrador es solo previsualización; el envío a aprobación
        // (transición + apartado + cadena) ocurre en enviarAprobacion().

        // Columnas de firma: solo los niveles que aplican a este tipo de
        // documento y al departamento de la solicitud.
        $solicitudPago->load('aprobaciones.aprobador');
        $firmasPdf = app(FirmasPdfBuilder::class)->build(
            $solicitudPago->tipoAprobacion(),
            $solicitudPago->departamento_id,
            $solicitudPago->aprobaciones,
        );

        // Las solicitudes generadas por una OC llevan un bloque de firma fijo de
        // dos espacios (usuario de compras que la elaboró + gerente de compras),
        // en vez de las columnas de la cadena por niveles.
        $firmasOc = $solicitudPago->orden_compra_id !== null
            ? $this->firmasOrdenCompra($solicitudPago)
            : null;

        $pdf = Pdf::loadView('pdf.costos.formato-solicitud-pago', [
            'solicitud' => $solicitudPago,
            'firmasPdf' => $firmasPdf,
            'firmasOc' => $firmasOc,
        ])->setPaper('letter', 'portrait')
            ->setOption('margin-top', 30)
            ->setOption('margin-bottom', 60)
            ->setOption('margin-left', 60)
            ->setOption('margin-right', 60);

        $filename = "solicitud-pago-{$solicitudPago->folio}.pdf";

        // Inline por defecto (para previsualizar en el modal de la OC); descarga
        // solo si se pide explícitamente con ?download=1.
        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /**
     * Bloque de firma de dos espacios para el PDF de una solicitud generada por
     * OC: el usuario de compras que la elaboró (espacio manual) y el gerente de
     * compras, cuya firma digital aparece una vez que aprobó.
     *
     * @return list<object{nombre: string, rol: string, firma_path: ?string, fecha: ?string}>
     */
    private function firmasOrdenCompra(SolicitudPago $solicitudPago): array
    {
        $aprobacionGerente = $solicitudPago->aprobaciones
            ->sortBy('nivel')
            ->first(fn ($a) => $a->estatus === AprobacionEstatus::Aprobada);

        $gerenteConfigurado = ConfiguracionCostos::actual()->gerenteCompras;

        return [
            (object) [
                'nombre' => $solicitudPago->solicitante?->name ?? 'Usuario de compras',
                'rol' => 'Elaboró · Compras',
                'firma_path' => null,
                'fecha' => null,
            ],
            (object) [
                'nombre' => $aprobacionGerente?->aprobador?->name
                    ?? $gerenteConfigurado?->name
                    ?? 'Gerente de compras',
                'rol' => 'Gerente de compras',
                'firma_path' => $aprobacionGerente?->aprobador?->firma_path,
                'fecha' => $aprobacionGerente?->fecha_respuesta?->format('d/m/Y H:i'),
            ],
        ];
    }

    public function uploadFirmado(SolicitudFirmadoRequest $request, SolicitudPago $solicitudPago): RedirectResponse
    {
        Gate::authorize('costos.solicitudes-pago.editar');

        if ($solicitudPago->estatus !== SolicitudPagoEstatus::PendienteFirma) {
            return back()->withErrors(['estatus' => 'La solicitud debe estar en pendiente de firma.']);
        }

        $file = $request->file('archivo');
        $path = $file->store('costos/firmados', 'public');

        DB::transaction(function () use ($solicitudPago, $file, $path, $request) {
            $solicitudPago->media()->create([
                'descripcion' => DocumentoTipo::SolicitudFirmada->value,
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
            $solicitudPago->transitionTo(SolicitudPagoEstatus::Aprobada);

            // Marcar aprobaciones
            $solicitudPago->aprobaciones()->update([
                'estatus' => 'aprobada',
                'fecha_respuesta' => now(),
            ]);

            // Aplicar impacto presupuestal: convierte el apartado vigente a ejercido
            // (o aplica desde cero). Evita el doble conteo apartado + aplicado.
            $solicitudPago->aplicarImpactoTrasFirma($request->user()->id);
        });

        return back()->with('success', 'Solicitud aprobada correctamente.');
    }

    public function cancelar(CancelarRequest $request, SolicitudPago $solicitudPago): RedirectResponse
    {
        // Un operador del módulo puede cancelar cualquier solicitud; el
        // solicitante puede cancelar las suyas si tiene el permiso para ello.
        $esPropia = $solicitudPago->solicitante_id === $request->user()->id;
        abort_unless(
            $request->user()->can('costos.solicitudes-pago.editar')
                || ($esPropia && $request->user()->can('costos.solicitudes-pago.cancelar-propia')),
            403,
        );

        if (! in_array($solicitudPago->estatus, [SolicitudPagoEstatus::Borrador, SolicitudPagoEstatus::PendienteFirma, SolicitudPagoEstatus::Aprobada], true)) {
            return back()->withErrors(['estatus' => 'Solo se pueden cancelar solicitudes en borrador, pendientes o aprobadas.']);
        }

        DB::transaction(function () use ($solicitudPago, $request) {
            // Revertir impacto si estaba aprobada. `cancelarApartadosDe` revierte por
            // rubro afectado (no en agregado) y marca cada uno Cancelado. Para las SP
            // generadas desde OC no revierte nada — la SP no tiene rubros afectados
            // propios (su presupuesto vive en la OC), que es el comportamiento correcto.
            if ($solicitudPago->estatus === SolicitudPagoEstatus::Aprobada) {
                $this->apartado->cancelarApartadosDe($solicitudPago, 'Cancelación de solicitud');
            }

            // Cancelar las aprobaciones pendientes de la cadena para que salgan de
            // la bandeja de aprobación (mismo criterio que un rechazo). No se borran:
            // quedan como Cancelada para conservar el historial.
            $solicitudPago->cadenaAprobacion()
                ->where('estatus', AprobacionEstatus::Pendiente->value)
                ->update([
                    'estatus' => AprobacionEstatus::Cancelada->value,
                    'fecha_respuesta' => now(),
                ]);

            $solicitudPago->transitionTo(SolicitudPagoEstatus::Cancelada);
            $solicitudPago->registrarCancelacion($request->validated('motivo'), $request->user()->id);
        });

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

        DB::transaction(function () use ($solicitudPago, $request) {
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
                    'tipo_cambio' => $solicitudPago->tipo_cambio ?? 1,
                    'tipo_pago' => 'contado',
                    'fecha_pago_programada' => $solicitudPago->fecha_pago_solicitada,
                    'estatus' => 'programado',
                ]);
            }
        });

        return back()->with('success', $solicitudPago->tipo_pago !== 'credito'
            ? 'Solicitud confirmada y pago programado.'
            : 'Solicitud confirmada por costos. Pendiente confirmación de contabilidad.');
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

        DB::transaction(function () use ($solicitudPago, $request) {
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
                'tipo_cambio' => $solicitudPago->tipo_cambio ?? 1,
                'tipo_pago' => 'credito',
                'fecha_pago_programada' => $solicitudPago->fecha_pago_solicitada,
                'estatus' => 'programado',
            ]);
        });

        return back()->with('success', 'Solicitud confirmada por contabilidad y pago programado.');
    }
}
