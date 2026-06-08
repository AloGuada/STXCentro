<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\AprobacionEstatus;
use App\Enums\Costos\RequisicionEstatus;
use App\Enums\ProveedorEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\CancelarRequest;
use App\Http\Requests\Admin\Costos\FirmarRequisicionFinalRequest;
use App\Http\Requests\Admin\Costos\RequisicionLiberarRequest;
use App\Http\Requests\Admin\Costos\RequisicionStoreRequest;
use App\Http\Requests\Admin\Costos\RequisicionUpdateRequest;
use App\Models\Costos\Aprobacion;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Costos\UsoCfdi;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Services\Costos\ApartadoPresupuestal;
use App\Services\Costos\ApprovalChainService;
use App\Services\Costos\AprobacionService;
use App\Services\Costos\OrdenCompraGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class RequisicionController extends Controller
{
    public function __construct(private readonly ApartadoPresupuestal $apartado) {}

    public function index(Request $request): Response
    {
        Gate::authorize('costos.requisiciones.ver');

        $requisiciones = Requisicion::query()
            ->with([
                'solicitante:id,name',
                'departamento:id,descripcion',
                'detalles:id,requisicion_id,cantidad',
                'detalles.cotizaciones:id,requisicion_detalle_id,proveedor_id,precio_unitario',
                'detalles.cotizaciones.proveedor:id,razon_social,nombre_comercial',
            ])
            ->when($request->search, fn ($q, $s) => $q->where('folio', 'like', "%{$s}%"))
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->when($request->departamento_id, fn ($q, $d) => $q->where('departamento_id', $d))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $requisiciones->getCollection()->each(fn ($r) => $r->append(['mejor_proveedor', 'proveedores_cotizadores_count']));

        return Inertia::render('admin/costos/requisiciones/index', [
            'requisiciones' => $requisiciones,
            'filters' => $request->only('search', 'estatus', 'departamento_id'),
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('costos.requisiciones.crear');

        return Inertia::render('admin/costos/requisiciones/create', [
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'obras' => Obra::orderBy('descripcion')->get(['id', 'no', 'descripcion']),
            'obraRubros' => $this->obraRubrosOptions(),
            'usosCfdi' => $this->usosCfdiOptions(),
        ]);
    }

    public function store(RequisicionStoreRequest $request): RedirectResponse
    {
        $requisicion = DB::transaction(function () use ($request) {
            $requisicion = Requisicion::create([
                'solicitante_id' => $request->user()->id,
                'departamento_id' => $request->integer('departamento_id'),
                'obra_id' => $request->integer('obra_id'),
                'justificacion' => $request->input('justificacion'),
                'fecha_requerida' => $request->input('fecha_requerida'),
                'estatus' => RequisicionEstatus::Borrador->value,
            ]);

            foreach ($request->input('detalles', []) as $d) {
                $requisicion->detalles()->create([
                    'descripcion' => $d['descripcion'],
                    'codigo_producto' => $d['codigo_producto'] ?? null,
                    'unidad' => $d['unidad'] ?? 'pza',
                    'cantidad' => $d['cantidad'],
                    'obra_rubro_id' => $d['obra_rubro_id'],
                    'uso_cfdi_id' => $d['uso_cfdi_id'],
                    'tipo_fiscal' => $d['tipo_fiscal'] ?? 'mercancia',
                    'notas' => $d['notas'] ?? null,
                ]);
            }

            return $requisicion;
        });

        return to_route('admin.costos.requisiciones.show', $requisicion)
            ->with('success', 'Requisición creada correctamente.');
    }

    /**
     * Duplica una requisición (en cualquier estado) en una nueva en borrador con
     * folio nuevo: copia partidas y cotizaciones (precios, proveedores, código,
     * días). NO copia selecciones, OCs ni aprobaciones. Útil para Compras para
     * re-cotizar o editar sin tocar la original.
     */
    public function duplicar(Requisicion $requisicion): RedirectResponse
    {
        // Duplicar es una acción de Compras (cotización), no del solicitante.
        Gate::authorize('costos.requisiciones.cotizar');

        $nueva = DB::transaction(function () use ($requisicion) {
            $nueva = Requisicion::create([
                'solicitante_id' => request()->user()->id,
                'departamento_id' => $requisicion->departamento_id,
                'obra_id' => $requisicion->obra_id,
                'justificacion' => $requisicion->justificacion,
                'fecha_requerida' => $requisicion->fecha_requerida,
                'estatus' => RequisicionEstatus::Borrador->value,
            ]);

            $requisicion->load('detalles.cotizaciones');

            foreach ($requisicion->detalles as $detalle) {
                $nuevoDetalle = $nueva->detalles()->create([
                    'descripcion' => $detalle->descripcion,
                    'codigo_producto' => $detalle->codigo_producto,
                    'unidad' => $detalle->unidad,
                    'cantidad' => $detalle->cantidad,
                    'obra_rubro_id' => $detalle->obra_rubro_id,
                    'uso_cfdi_id' => $detalle->uso_cfdi_id,
                    'tipo_fiscal' => $detalle->tipo_fiscal,
                    'notas' => $detalle->notas,
                ]);

                foreach ($detalle->cotizaciones as $cotizacion) {
                    $nuevoDetalle->cotizaciones()->create([
                        'proveedor_id' => $cotizacion->proveedor_id,
                        'precio_unitario' => $cotizacion->precio_unitario,
                        'codigo_producto' => $cotizacion->codigo_producto,
                        'moneda' => $cotizacion->moneda,
                        'tiempo_entrega_dias' => $cotizacion->tiempo_entrega_dias,
                        'observaciones' => $cotizacion->observaciones,
                        'media_id' => $cotizacion->media_id,
                    ]);
                }
            }

            return $nueva;
        });

        return to_route('admin.costos.requisiciones.show', $nueva)
            ->with('success', "Requisición duplicada en {$nueva->folio} (borrador).");
    }

    public function show(Requisicion $requisicion): Response
    {
        Gate::authorize('costos.requisiciones.ver');

        $requisicion->load([
            'solicitante:id,name',
            'departamento:id,descripcion',
            'obra:id,no,descripcion',
            'detalles.obraRubro.obra:id,no,descripcion',
            'detalles.obraRubro.rubro:id,codigo,descripcion',
            'detalles.usoCfdi:id,clave,descripcion',
            'detalles.cotizaciones.proveedor:id,razon_social,nombre_comercial,estatus,activo',
            'detalles.selecciones.cotizacionPrecio',
            'detalles.selecciones.proveedor:id,razon_social,estatus',
            'aprobaciones.aprobador:id,name',
            'ordenesGeneradas:id,folio,proveedor_id,total,estatus,requisicion_id',
            'ordenesGeneradas.proveedor:id,razon_social',
            'activities.causer',
        ]);

        $aprobacionPendienteId = $this->aprobacionPendienteParaUsuario($requisicion);
        $esUltimoNivel = $this->esUltimoNivel($requisicion, $aprobacionPendienteId);

        return Inertia::render('admin/costos/requisiciones/show', [
            'requisicion' => $requisicion,
            'proveedores' => Proveedor::whereIn('estatus', [ProveedorEstatus::PendienteValidacion->value, ProveedorEstatus::Activo->value])
                ->with('regimenFiscal:id,clave')
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nombre_comercial', 'maneja_credito', 'estatus', 'tipo_persona', 'regimen_fiscal_id']),
            'obraRubros' => $this->obraRubrosOptions(),
            'aprobacionPendienteId' => $aprobacionPendienteId,
            'esUltimoNivel' => $esUltimoNivel,
            'proveedoresPorValidar' => $esUltimoNivel ? $this->proveedoresPorValidar($requisicion) : [],
        ]);
    }

    /**
     * ¿La aprobación pendiente del usuario es el último nivel? Lo es cuando no
     * existe ninguna aprobación pendiente en un nivel superior.
     */
    private function esUltimoNivel(Requisicion $requisicion, ?int $aprobacionPendienteId): bool
    {
        if (! $aprobacionPendienteId) {
            return false;
        }

        $mia = $requisicion->aprobaciones->firstWhere('id', $aprobacionPendienteId);
        if (! $mia) {
            return false;
        }

        return $requisicion->aprobaciones
            ->where('nivel', '>', $mia->nivel)
            ->where('estatus', 'pendiente')
            ->isEmpty();
    }

    /**
     * Proveedores referenciados por las selecciones que aún no están activos y
     * por tanto requieren validación documental antes de aprobar. Incluye sus
     * documentos, las partidas que ganaron y las cotizaciones alternativas
     * (otros proveedores que cotizaron esas mismas partidas) para reasignar.
     *
     * @return list<array<string, mixed>>
     */
    private function proveedoresPorValidar(Requisicion $requisicion): array
    {
        $selecciones = $requisicion->detalles->flatMap->selecciones;
        $provIds = $selecciones->pluck('proveedor_id')->unique()->filter()->all();

        if (empty($provIds)) {
            return [];
        }

        $proveedores = Proveedor::with(['regimenFiscal:id,clave,descripcion', 'media'])
            ->whereIn('id', $provIds)
            ->where('estatus', '!=', ProveedorEstatus::Activo->value)
            ->get();

        return $proveedores->map(function (Proveedor $prov) use ($requisicion, $selecciones) {
            $detalleIds = $selecciones->where('proveedor_id', $prov->id)
                ->pluck('requisicion_detalle_id')->unique();

            $partidas = $requisicion->detalles
                ->whereIn('id', $detalleIds)
                ->map(function (RequisicionDetalle $d) use ($prov) {
                    $alternativas = $d->cotizaciones
                        ->where('proveedor_id', '!=', $prov->id)
                        ->filter(fn ($c) => $c->proveedor && $c->proveedor->estatus === ProveedorEstatus::Activo)
                        ->map(fn ($c) => [
                            'cotizacion_precio_id' => $c->id,
                            'proveedor_id' => $c->proveedor_id,
                            'proveedor' => $c->proveedor?->razon_social,
                            'precio_unitario' => (float) $c->precio_unitario,
                        ])->values();

                    return [
                        'requisicion_detalle_id' => $d->id,
                        'descripcion' => $d->descripcion,
                        'alternativas' => $alternativas,
                    ];
                })->values();

            $url = fn (?string $desc) => ($m = $prov->media->firstWhere('descripcion', $desc))
                ? Storage::disk('public')->url($m->path)
                : null;

            return [
                'id' => $prov->id,
                'razon_social' => $prov->razon_social,
                'rfc' => $prov->rfc,
                'tipo_persona' => $prov->tipo_persona,
                'regimen' => $prov->regimenFiscal?->descripcion,
                'banco' => $prov->banco,
                'titular_cuenta' => $prov->titular_cuenta,
                'numero_cuenta' => $prov->numero_cuenta,
                'clabe' => $prov->clabe,
                'moneda_cuenta' => $prov->moneda_cuenta,
                'constancia_url' => $url('constancia_fiscal'),
                'caratula_url' => $url('caratula_bancaria'),
                'partidas' => $partidas,
            ];
        })->values()->all();
    }

    /**
     * Firma del último nivel con validación documental de proveedores. Por cada
     * proveedor pendiente: se activa, o se rechaza reasignando sus partidas a un
     * proveedor que ya cotizó. Si algún proveedor queda rechazado sin reemplazo
     * (o queda alguna selección apuntando a proveedor no activo), se rechaza la
     * requisición. En caso contrario se firma y la requisición pasa a aprobada.
     */
    public function firmarFinal(FirmarRequisicionFinalRequest $request, Requisicion $requisicion, AprobacionService $aprobaciones): RedirectResponse
    {
        $requisicion->load([
            'aprobaciones',
            'detalles.selecciones.proveedor:id,estatus',
            'detalles.cotizaciones',
        ]);

        $aprobacionPendienteId = $this->aprobacionPendienteParaUsuario($requisicion);
        if (! $aprobacionPendienteId) {
            return back()->withErrors(['nivel' => 'No tiene una firma pendiente en turno para esta requisición.']);
        }

        if (! $this->esUltimoNivel($requisicion, $aprobacionPendienteId)) {
            return back()->withErrors(['nivel' => 'La validación de proveedores solo se realiza en el último nivel.']);
        }

        /** @var Aprobacion $aprobacion */
        $aprobacion = $requisicion->aprobaciones->firstWhere('id', $aprobacionPendienteId);

        DB::transaction(function () use ($request, $requisicion, $aprobacion, $aprobaciones) {
            $rechazoSinReemplazo = false;

            foreach ($request->input('validaciones', []) as $val) {
                /** @var Proveedor $proveedor */
                $proveedor = Proveedor::findOrFail($val['proveedor_id']);

                $proveedor->update([
                    'estatus' => $val['accion'] === 'activar' ? ProveedorEstatus::Activo : ProveedorEstatus::Rechazado,
                    'activo' => $val['accion'] === 'activar',
                    'validado_por' => auth()->id(),
                    'validado_at' => now(),
                    'observacion_validacion' => $val['observacion'] ?? null,
                ]);

                if ($val['accion'] === 'activar') {
                    continue;
                }

                $reemplazos = $val['reemplazos'] ?? [];
                if (empty($reemplazos)) {
                    $rechazoSinReemplazo = true;

                    continue;
                }

                foreach ($reemplazos as $r) {
                    $cotizacion = RequisicionCotizacionPrecio::where('id', $r['cotizacion_precio_id'])
                        ->where('requisicion_detalle_id', $r['requisicion_detalle_id'])
                        ->where('proveedor_id', $r['nuevo_proveedor_id'])
                        ->firstOrFail();

                    RequisicionSeleccion::where('requisicion_detalle_id', $r['requisicion_detalle_id'])
                        ->where('proveedor_id', $proveedor->id)
                        ->update([
                            'proveedor_id' => $cotizacion->proveedor_id,
                            'cotizacion_precio_id' => $cotizacion->id,
                        ]);
                }
            }

            // Tras reasignar, ninguna selección puede apuntar a un proveedor no activo.
            $requisicion->load('detalles.selecciones.proveedor:id,estatus');
            $hayInvalidas = $requisicion->detalles->flatMap->selecciones
                ->contains(fn (RequisicionSeleccion $s) => $s->proveedor?->estatus !== ProveedorEstatus::Activo);

            if ($rechazoSinReemplazo || $hayInvalidas) {
                $motivo = $request->input('observaciones');
                $aprobacion->update([
                    'fecha_respuesta' => now(),
                    'observaciones' => $motivo,
                    'motivo_rechazo' => $motivo,
                    'ip' => $request->ip(),
                    'hostname' => gethostbyaddr($request->ip()) ?: null,
                ]);
                $aprobacion->transitionTo(AprobacionEstatus::Rechazada);
                $requisicion->cadenaAprobacion()
                    ->where('estatus', 'pendiente')
                    ->update(['estatus' => 'cancelada', 'fecha_respuesta' => now()]);
                $requisicion->onAprobacionRechazada($motivo, auth()->id());

                return;
            }

            $aprobaciones->aprobar(
                $aprobacion,
                $request->input('observaciones'),
                $request->ip(),
                gethostbyaddr($request->ip()) ?: null,
            );
        });

        return back()->with('success', 'Requisición firmada.');
    }

    /**
     * Devuelve el id de la aprobación pendiente del usuario actual cuando es
     * su turno en la cadena de firmas. Si no es su turno o no tiene firma
     * asignada, devuelve null.
     */
    private function aprobacionPendienteParaUsuario(Requisicion $requisicion): ?int
    {
        $userId = auth()->id();
        if (! $userId) {
            return null;
        }

        $mias = $requisicion->aprobaciones
            ->where('aprobador_id', $userId)
            ->where('estatus', 'pendiente');

        foreach ($mias as $aprobacion) {
            $nivelesAnteriores = $requisicion->aprobaciones
                ->where('nivel', '<', $aprobacion->nivel)
                ->pluck('nivel')
                ->unique();

            $todosAprobados = $nivelesAnteriores->every(
                fn ($nivel) => $requisicion->aprobaciones
                    ->where('nivel', $nivel)
                    ->where('estatus', 'aprobada')
                    ->isNotEmpty()
            );

            if ($todosAprobados) {
                return $aprobacion->id;
            }
        }

        return null;
    }

    public function edit(Requisicion $requisicion): Response
    {
        Gate::authorize('costos.requisiciones.crear');

        if (! in_array($requisicion->estatus, [RequisicionEstatus::Borrador, RequisicionEstatus::Rechazada], true)) {
            abort(403, 'Solo se pueden editar requisiciones en borrador o rechazadas.');
        }

        $requisicion->lock(auth()->id());
        $requisicion->load(['detalles', 'departamento']);

        return Inertia::render('admin/costos/requisiciones/edit', [
            'requisicion' => $requisicion,
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'obras' => Obra::orderBy('descripcion')->get(['id', 'no', 'descripcion']),
            'obraRubros' => $this->obraRubrosOptions(),
            'usosCfdi' => $this->usosCfdiOptions(),
        ]);
    }

    public function update(RequisicionUpdateRequest $request, Requisicion $requisicion): RedirectResponse
    {
        if (! in_array($requisicion->estatus, [RequisicionEstatus::Borrador, RequisicionEstatus::Rechazada], true)) {
            return back()->withErrors(['estatus' => 'Solo se pueden editar requisiciones en borrador o rechazadas.']);
        }

        $requisicion->assertVersion($request->input('_version'));

        DB::transaction(function () use ($request, $requisicion) {
            $requisicion->update([
                'departamento_id' => $request->integer('departamento_id'),
                'obra_id' => $request->integer('obra_id'),
                'justificacion' => $request->input('justificacion'),
                'fecha_requerida' => $request->input('fecha_requerida'),
            ]);

            $idsKeep = collect($request->input('detalles', []))
                ->pluck('id')
                ->filter()
                ->all();

            $requisicion->detalles()
                ->whereNotIn('id', $idsKeep)
                ->delete();

            foreach ($request->input('detalles', []) as $d) {
                if (! empty($d['id'])) {
                    // codigo_producto y tipo_fiscal se gestionan en el tab de
                    // cotización (Compras), no en la edición de la requisición:
                    // no se tocan aquí para no pisar lo capturado.
                    RequisicionDetalle::where('id', $d['id'])
                        ->where('requisicion_id', $requisicion->id)
                        ->update([
                            'descripcion' => $d['descripcion'],
                            'unidad' => $d['unidad'] ?? 'pza',
                            'cantidad' => $d['cantidad'],
                            'obra_rubro_id' => $d['obra_rubro_id'],
                            'uso_cfdi_id' => $d['uso_cfdi_id'],
                            'notas' => $d['notas'] ?? null,
                        ]);
                } else {
                    $requisicion->detalles()->create([
                        'descripcion' => $d['descripcion'],
                        'codigo_producto' => $d['codigo_producto'] ?? null,
                        'unidad' => $d['unidad'] ?? 'pza',
                        'cantidad' => $d['cantidad'],
                        'obra_rubro_id' => $d['obra_rubro_id'],
                        'uso_cfdi_id' => $d['uso_cfdi_id'],
                        'tipo_fiscal' => $d['tipo_fiscal'] ?? 'mercancia',
                        'notas' => $d['notas'] ?? null,
                    ]);
                }
            }

            // Si venia de rechazada, vuelve a borrador para empezar nueva ronda.
            if ($requisicion->estatus === RequisicionEstatus::Rechazada) {
                $requisicion->transitionTo(RequisicionEstatus::Borrador);
            }
        });

        $requisicion->unlock($request->user()->id);

        return to_route('admin.costos.requisiciones.show', $requisicion)
            ->with('success', 'Requisición actualizada.');
    }

    public function cancelar(CancelarRequest $request, Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cancelar');

        if (in_array($requisicion->estatus, [RequisicionEstatus::Liberada, RequisicionEstatus::Cancelada], true)) {
            return back()->withErrors(['estatus' => 'No se puede cancelar una requisición en este estado.']);
        }

        DB::transaction(function () use ($request, $requisicion) {
            $requisicion->cadenaAprobacion()
                ->where('estatus', 'pendiente')
                ->update(['estatus' => 'cancelada', 'fecha_respuesta' => now()]);

            app(\App\Services\Costos\ApartadoPresupuestal::class)
                ->cancelarApartadosDe($requisicion, 'requisición cancelada');

            $requisicion->transitionTo(RequisicionEstatus::Cancelada);
            $requisicion->registrarCancelacion($request->validated('motivo'), $request->user()->id);
        });

        return back()->with('success', 'Requisición cancelada.');
    }

    /**
     * Envia la requisicion a la cadena de firmas. Solo permitido en `cotizada`
     * y exige captura completa: rubro por detalle, modo de pago, partidas
     * cubiertas 100% por selecciones, y cada seleccion con precio capturado.
     */
    public function enviarAprobacion(Request $request, Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        if ($requisicion->estatus !== RequisicionEstatus::Cotizada) {
            return back()->withErrors(['estatus' => 'La requisición debe estar cotizada para enviarse a aprobación.']);
        }

        $requisicion->load('detalles.selecciones.cotizacionPrecio');

        foreach ($requisicion->detalles as $detalle) {
            if (empty($detalle->uso_cfdi_id)) {
                return back()->withErrors([
                    'detalles' => "La partida \"{$detalle->descripcion}\" no tiene uso de CFDI asignado.",
                ]);
            }

            if (empty($detalle->obra_rubro_id)) {
                return back()->withErrors([
                    'detalles' => "La partida \"{$detalle->descripcion}\" no tiene centro de costos asignado.",
                ]);
            }

            $sumaSelecciones = (float) $detalle->selecciones->sum('cantidad');
            $cantidadPartida = (float) $detalle->cantidad;

            if ($sumaSelecciones <= 0.0) {
                return back()->withErrors([
                    'selecciones' => "La partida \"{$detalle->descripcion}\" no tiene proveedor asignado.",
                ]);
            }

            if ($sumaSelecciones + 0.001 < $cantidadPartida) {
                return back()->withErrors([
                    'selecciones' => "La partida \"{$detalle->descripcion}\" no está cubierta al 100% por las selecciones.",
                ]);
            }

            if ($sumaSelecciones > $cantidadPartida + 0.001) {
                return back()->withErrors([
                    'selecciones' => "La partida \"{$detalle->descripcion}\" tiene selecciones por encima de la cantidad solicitada.",
                ]);
            }

            foreach ($detalle->selecciones as $sel) {
                if (! $sel->cotizacionPrecio || ! $sel->cotizacionPrecio->precio_unitario) {
                    return back()->withErrors([
                        'selecciones' => "La partida \"{$detalle->descripcion}\" tiene una selección sin precio cotizado.",
                    ]);
                }
            }
        }

        DB::transaction(function () use ($request, $requisicion) {
            app(ApprovalChainService::class)->crearCadenaAprobaciones($requisicion);

            $requisicion->transitionTo(RequisicionEstatus::PendienteAprobacion);

            // Apartado temporal de presupuesto (5 días): cada partida usa
            // el monto de sus selecciones (∑ cantidad × precio cotizado).
            $items = $requisicion->detalles
                ->filter(fn ($d) => $d->obra_rubro_id)
                ->map(fn ($d) => [
                    'obra_rubro_id' => (int) $d->obra_rubro_id,
                    'monto' => (float) $d->selecciones->sum(
                        fn ($s) => (float) $s->cantidad * (float) ($s->cotizacionPrecio?->precio_unitario ?? 0)
                    ),
                    'descripcion' => $d->descripcion,
                ])
                ->filter(fn ($i) => $i['monto'] > 0);

            app(\App\Services\Costos\ApartadoPresupuestal::class)
                ->apartarDocumento($requisicion, $items, $request->user()->id);
        });

        return back()->with('success', 'Requisición enviada a aprobación.');
    }

    /**
     * Compras libera la requisicion aprobada: agrupa selecciones por
     * (proveedor, numero_oc), crea N OCs con su rubro heredado del detalle.
     * El payload `ocs[]` define modo_pago, notas y fecha por OC.
     * `total` = subtotal_lineas + IVA(16%). Aplica impacto
     * presupuestal en la misma transaccion. Idempotente: si ya hay OCs,
     * bloquea.
     */
    public function liberar(RequisicionLiberarRequest $request, Requisicion $requisicion, OrdenCompraGenerator $generator): RedirectResponse
    {
        if ($requisicion->estatus !== RequisicionEstatus::Aprobada) {
            return back()->withErrors(['estatus' => 'Solo requisiciones aprobadas se pueden liberar.']);
        }

        if ($requisicion->ordenesGeneradas()->exists()) {
            return back()->withErrors(['estatus' => 'Esta requisición ya tiene OCs generadas.']);
        }

        $requisicion->load([
            'detalles.selecciones.cotizacionPrecio',
            'detalles.selecciones.proveedor',
        ]);

        foreach ($requisicion->detalles as $detalle) {
            if (empty($detalle->uso_cfdi_id)) {
                return back()->withErrors([
                    'detalles' => "La partida \"{$detalle->descripcion}\" no tiene uso de CFDI asignado.",
                ]);
            }

            if (empty($detalle->obra_rubro_id)) {
                return back()->withErrors([
                    'detalles' => "La partida \"{$detalle->descripcion}\" no tiene centro de costos asignado.",
                ]);
            }
        }

        $todasSelecciones = $requisicion->detalles->flatMap->selecciones;

        // Indexa el payload `ocs[]` por (proveedor_id, numero_oc) para
        // resolver overrides (modo_pago, notas, fecha_entrega) por OC.
        $ocsPayload = collect($request->input('ocs', []))
            ->keyBy(fn ($oc) => $oc['proveedor_id'].'|'.$oc['numero_oc']);

        $grupos = $todasSelecciones->groupBy(
            fn (RequisicionSeleccion $s) => $s->proveedor_id.'|'.((int) ($s->numero_oc ?: 1))
        );

        foreach ($grupos as $key => $selecciones) {
            if (! $ocsPayload->has($key)) {
                return back()->withErrors([
                    'ocs' => "Falta capturar la OC para la combinación proveedor-OC# {$key}.",
                ]);
            }

            // Una OC no puede mezclar monedas: todas sus cotizaciones deben coincidir.
            $monedas = $selecciones
                ->map(fn (RequisicionSeleccion $s) => $s->cotizacionPrecio?->moneda ?? 'mxn')
                ->unique();

            if ($monedas->count() > 1) {
                return back()->withErrors([
                    'ocs' => "La OC del grupo {$key} mezcla monedas (".$monedas->implode(', ').'). Separa las partidas por moneda en OCs distintas.',
                ]);
            }

            $proveedor = $selecciones->first()?->proveedor;
            if ($proveedor?->bloqueadoPorComplemento()) {
                return back()->withErrors([
                    'ocs' => "El proveedor \"{$proveedor->razon_social}\" está bloqueado por un complemento de pago pendiente. No se puede liberar la OC hasta regularizar.",
                ]);
            }
        }

        $generator->generar($requisicion, $grupos, $ocsPayload, $request->user()->id);

        return to_route('admin.costos.requisiciones.show', $requisicion)
            ->with('success', 'Requisición liberada y órdenes de compra generadas.');
    }

    /**
     * Re-aparta el presupuesto de una requisición cuyos apartados vencieron.
     * Solo aplica antes de liberar la requisición (estados PendienteAprobacion
     * o Aprobada). Si todavía tiene apartados vigentes, no hace nada.
     */
    public function reApartar(Request $request, Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        if (! in_array($requisicion->estatus, [RequisicionEstatus::PendienteAprobacion, RequisicionEstatus::Aprobada], true)) {
            return back()->withErrors(['estatus' => 'Solo se puede re-apartar una requisición pendiente o aprobada, antes de liberarse.']);
        }

        $requisicion->load('detalles.selecciones.cotizacionPrecio');

        $items = $requisicion->detalles
            ->filter(fn ($d) => $d->obra_rubro_id)
            ->map(fn ($d) => [
                'obra_rubro_id' => (int) $d->obra_rubro_id,
                'monto' => (float) $d->selecciones->sum(
                    fn ($s) => (float) $s->cantidad * (float) ($s->cotizacionPrecio?->precio_unitario ?? 0)
                ),
                'descripcion' => $d->descripcion,
            ])
            ->filter(fn ($i) => $i['monto'] > 0);

        $this->apartado->reApartarDocumento($requisicion, $items, $request->user()->id);

        return back()->with('success', 'Presupuesto re-apartado por 5 días.');
    }

    /**
     * Lista de obra-rubros con info presupuestal para selectores.
     * `disponible` = presupuestado - acumulado; `sobregiro` cuando es negativo.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, label: string, presupuestado: float, acumulado: float, disponible: float, sobregiro: bool}>
     */
    private function obraRubrosOptions(): \Illuminate\Support\Collection
    {
        return ObraRubro::with(['obra:id,no,descripcion', 'rubro:id,codigo,descripcion'])
            ->get()
            ->map(function ($or) {
                $disponible = $or->disponible;

                $opPrefix = $or->obra?->no ? 'OP-'.$or->obra->no.' · ' : '';

                return [
                    'id' => $or->id,
                    'obra_id' => $or->obra_id,
                    'obra_label' => trim($opPrefix.($or->obra?->descripcion ?? '-')),
                    'rubro_label' => trim(sprintf('%s %s', $or->rubro?->codigo ?? '', $or->rubro?->descripcion ?? '-')),
                    'label' => sprintf(
                        '%s%s · %s %s',
                        $opPrefix,
                        $or->obra?->descripcion ?? '-',
                        $or->rubro?->codigo ?? '',
                        $or->rubro?->descripcion ?? '-',
                    ),
                    'presupuestado' => (float) $or->presupuestado,
                    'acumulado' => (float) $or->acumulado,
                    'disponible' => $disponible,
                    'sobregiro' => $disponible < 0,
                ];
            })
            ->values();
    }

    /**
     * Catálogo de usos de CFDI activos para los selectores de partida.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Costos\UsoCfdi>
     */
    private function usosCfdiOptions(): \Illuminate\Support\Collection
    {
        return UsoCfdi::where('activo', true)
            ->orderBy('clave')
            ->get(['id', 'clave', 'descripcion']);
    }
}
