<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\OrdenCompraEstatus;
use App\Enums\Costos\RequisicionEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\CancelarRequest;
use App\Http\Requests\Admin\Costos\RequisicionLiberarRequest;
use App\Http\Requests\Admin\Costos\RequisicionStoreRequest;
use App\Http\Requests\Admin\Costos\RequisicionUpdateRequest;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Departamento;
use App\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RequisicionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('costos.requisiciones.ver');

        $requisiciones = Requisicion::query()
            ->with(['solicitante:id,name', 'departamento:id,descripcion'])
            ->when($request->search, fn ($q, $s) => $q->where('folio', 'like', "%{$s}%"))
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->when($request->departamento_id, fn ($q, $d) => $q->where('departamento_id', $d))
            ->latest()
            ->paginate(15)
            ->withQueryString();

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
            'obraRubros' => $this->obraRubrosOptions(),
        ]);
    }

    public function store(RequisicionStoreRequest $request): RedirectResponse
    {
        $requisicion = DB::transaction(function () use ($request) {
            $requisicion = Requisicion::create([
                'solicitante_id' => $request->user()->id,
                'departamento_id' => $request->integer('departamento_id'),
                'justificacion' => $request->input('justificacion'),
                'fecha_requerida' => $request->input('fecha_requerida'),
                'estatus' => RequisicionEstatus::Borrador->value,
            ]);

            foreach ($request->input('detalles', []) as $d) {
                $requisicion->detalles()->create([
                    'descripcion' => $d['descripcion'],
                    'unidad' => $d['unidad'] ?? 'pza',
                    'cantidad' => $d['cantidad'],
                    'obra_rubro_id' => $d['obra_rubro_id'],
                    'notas' => $d['notas'] ?? null,
                ]);
            }

            return $requisicion;
        });

        return to_route('admin.costos.requisiciones.show', $requisicion)
            ->with('success', 'Requisición creada correctamente.');
    }

    public function show(Requisicion $requisicion): Response
    {
        Gate::authorize('costos.requisiciones.ver');

        $requisicion->load([
            'solicitante:id,name',
            'departamento:id,descripcion',
            'detalles.obraRubro.obra:id,no,descripcion',
            'detalles.obraRubro.rubro:id,codigo,descripcion',
            'detalles.cotizaciones.proveedor:id,razon_social,nombre_comercial',
            'detalles.selecciones.cotizacionPrecio',
            'detalles.selecciones.proveedor:id,razon_social',
            'aprobaciones.aprobador:id,name',
            'ordenesGeneradas:id,folio,proveedor_id,total,estatus,requisicion_id',
            'ordenesGeneradas.proveedor:id,razon_social',
            'activities.causer',
        ]);

        return Inertia::render('admin/costos/requisiciones/show', [
            'requisicion' => $requisicion,
            'proveedores' => Proveedor::where('activo', true)
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nombre_comercial']),
            'obraRubros' => $this->obraRubrosOptions(),
            'aprobacionPendienteId' => $this->aprobacionPendienteParaUsuario($requisicion),
        ]);
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
            'obraRubros' => $this->obraRubrosOptions(),
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
                    RequisicionDetalle::where('id', $d['id'])
                        ->where('requisicion_id', $requisicion->id)
                        ->update([
                            'descripcion' => $d['descripcion'],
                            'unidad' => $d['unidad'] ?? 'pza',
                            'cantidad' => $d['cantidad'],
                            'obra_rubro_id' => $d['obra_rubro_id'],
                            'notas' => $d['notas'] ?? null,
                        ]);
                } else {
                    $requisicion->detalles()->create([
                        'descripcion' => $d['descripcion'],
                        'unidad' => $d['unidad'] ?? 'pza',
                        'cantidad' => $d['cantidad'],
                        'obra_rubro_id' => $d['obra_rubro_id'],
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
            if (empty($detalle->obra_rubro_id)) {
                return back()->withErrors([
                    'detalles' => "La partida \"{$detalle->descripcion}\" no tiene rubro asignado.",
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

        DB::transaction(function () use ($requisicion) {
            $cadena = AprobacionDepartamento::where('departamento_id', $requisicion->departamento_id)
                ->whereHas('permiso', fn ($q) => $q->where('tipo_aprobacion', Requisicion::TIPO_APROBACION))
                ->with('permiso')
                ->get()
                ->sortBy('permiso.nivel')
                ->values();

            foreach ($cadena as $asignacion) {
                $requisicion->aprobaciones()->create([
                    'nivel' => $asignacion->permiso->nivel,
                    'aprobador_id' => $asignacion->aprobador_id,
                    'estatus' => 'pendiente',
                ]);
            }

            $requisicion->transitionTo(RequisicionEstatus::PendienteAprobacion);
        });

        return back()->with('success', 'Requisición enviada a aprobación.');
    }

    /**
     * Compras libera la requisicion aprobada: agrupa selecciones por
     * (proveedor, numero_oc), crea N OCs con su rubro heredado del detalle.
     * El payload `ocs[]` define modo_pago, envio, notas y fecha por OC.
     * `total` = subtotal_lineas + envio + IVA(16%). Aplica impacto
     * presupuestal en la misma transaccion. Idempotente: si ya hay OCs,
     * bloquea.
     */
    public function liberar(RequisicionLiberarRequest $request, Requisicion $requisicion): RedirectResponse
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
            if (empty($detalle->obra_rubro_id)) {
                return back()->withErrors([
                    'detalles' => "La partida \"{$detalle->descripcion}\" no tiene rubro asignado.",
                ]);
            }
        }

        $todasSelecciones = $requisicion->detalles->flatMap->selecciones;

        // Indexa el payload `ocs[]` por (proveedor_id, numero_oc) para
        // resolver overrides (modo_pago, envio, notas, fecha_entrega) por OC.
        $ocsPayload = collect($request->input('ocs', []))
            ->keyBy(fn ($oc) => $oc['proveedor_id'].'|'.$oc['numero_oc']);

        $grupos = $todasSelecciones->groupBy(
            fn (RequisicionSeleccion $s) => $s->proveedor_id.'|'.((int) ($s->numero_oc ?: 1))
        );

        foreach ($grupos as $key => $_) {
            if (! $ocsPayload->has($key)) {
                return back()->withErrors([
                    'ocs' => "Falta capturar la OC para la combinación proveedor-OC# {$key}.",
                ]);
            }
        }

        $userId = $request->user()->id;

        DB::transaction(function () use ($requisicion, $grupos, $ocsPayload, $userId) {
            foreach ($grupos as $key => $selecciones) {
                $payload = $ocsPayload[$key];
                $proveedorId = (int) $payload['proveedor_id'];
                $numeroOc = (int) $payload['numero_oc'];
                $modoPago = (string) $payload['modo_pago'];
                $moneda = (string) $payload['moneda'];
                $envio = (float) ($payload['envio'] ?? 0);
                $notas = $payload['notas'] ?? null;

                // La fecha de entrega esperada se calcula a partir de los días
                // de entrega cotizados: hoy + max(tiempo_entrega_dias) de las
                // selecciones de esta OC. Si ninguna selección lo trae, queda
                // null (se asume "lo antes posible").
                $diasMax = $selecciones->max(
                    fn (RequisicionSeleccion $s) => (int) ($s->cotizacionPrecio?->tiempo_entrega_dias ?? 0)
                );
                $fechaEntrega = $diasMax > 0
                    ? now()->addDays($diasMax)->format('Y-m-d')
                    : null;

                $proveedor = Proveedor::find($proveedorId);

                $subtotalLineas = $selecciones->reduce(function ($acc, RequisicionSeleccion $s) {
                    $precio = (float) ($s->cotizacionPrecio?->precio_unitario ?? 0);

                    return $acc + $precio * (float) $s->cantidad;
                }, 0.0);

                $base = $subtotalLineas + $envio;
                $iva = $base * 0.16;
                $total = $base + $iva;

                $diasCredito = ($modoPago === 'credito' && $proveedor?->maneja_credito)
                    ? (int) ($proveedor->dias_credito_default ?? 0)
                    : 0;

                $oc = OrdenCompra::create([
                    'requisicion_id' => $requisicion->id,
                    'proveedor_id' => $proveedorId,
                    'departamento_id' => $requisicion->departamento_id,
                    'creado_por' => $userId,
                    'moneda' => $moneda,
                    'tipo_pago' => $modoPago,
                    'dias_credito' => $diasCredito,
                    'envio' => round($envio, 2),
                    'total' => round($total, 2),
                    'fecha_entrega_esperada' => $fechaEntrega,
                    'notas' => $notas,
                    'estatus' => OrdenCompraEstatus::PendienteFactura->value,
                ]);

                foreach ($selecciones as $sel) {
                    /** @var RequisicionSeleccion $sel */
                    $detalle = $sel->detalle ?? RequisicionDetalle::find($sel->requisicion_detalle_id);
                    $precioUnit = (float) ($sel->cotizacionPrecio?->precio_unitario ?? 0);
                    $cantidad = (float) $sel->cantidad;
                    $subtotal = round($precioUnit * $cantidad, 2);

                    $ocDetalle = OrdenCompraDetalle::create([
                        'orden_compra_id' => $oc->id,
                        'requisicion_detalle_id' => $sel->requisicion_detalle_id,
                        'obra_rubro_id' => $detalle->obra_rubro_id,
                        'descripcion' => $detalle->descripcion,
                        'unidad' => $detalle->unidad,
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precioUnit,
                        'subtotal' => $subtotal,
                    ]);

                    $sel->update([
                        'obra_rubro_id' => $detalle->obra_rubro_id,
                        'orden_compra_detalle_id' => $ocDetalle->id,
                    ]);
                }

                $oc->load('detalles');
                $oc->aplicarImpactoPresupuestal($userId);
            }

            $requisicion->transitionTo(RequisicionEstatus::Liberada);
        });

        return to_route('admin.costos.requisiciones.show', $requisicion)
            ->with('success', 'Requisición liberada y órdenes de compra generadas.');
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
}
