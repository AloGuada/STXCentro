<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\OrdenCompraEstatus;
use App\Enums\Costos\RequisicionEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\CancelarRequest;
use App\Http\Requests\Admin\Costos\GenerarOrdenesRequest;
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
            ->when($request->search, fn ($q, $s) => $q->where(function ($qq) use ($s) {
                $qq->where('folio', 'like', "%{$s}%")
                    ->orWhere('concepto', 'like', "%{$s}%");
            }))
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
        ]);
    }

    public function store(RequisicionStoreRequest $request): RedirectResponse
    {
        $requisicion = DB::transaction(function () use ($request) {
            $requisicion = Requisicion::create([
                'solicitante_id' => $request->user()->id,
                'departamento_id' => $request->integer('departamento_id'),
                'concepto' => $request->string('concepto'),
                'justificacion' => $request->input('justificacion'),
                'fecha_requerida' => $request->input('fecha_requerida'),
                'estatus' => RequisicionEstatus::Borrador->value,
            ]);

            foreach ($request->input('detalles', []) as $d) {
                $requisicion->detalles()->create([
                    'descripcion' => $d['descripcion'],
                    'unidad' => $d['unidad'] ?? 'pza',
                    'cantidad' => $d['cantidad'],
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
            'rubros' => ObraRubro::with(['obra:id,nombre', 'rubro:id,descripcion'])
                ->get()
                ->map(fn ($or) => [
                    'id' => $or->id,
                    'label' => sprintf('%s · %s', $or->obra?->nombre ?? '-', $or->rubro?->descripcion ?? '-'),
                ])
                ->values(),
        ]);
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
                'concepto' => $request->string('concepto'),
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
                            'notas' => $d['notas'] ?? null,
                        ]);
                } else {
                    $requisicion->detalles()->create([
                        'descripcion' => $d['descripcion'],
                        'unidad' => $d['unidad'] ?? 'pza',
                        'cantidad' => $d['cantidad'],
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

        if (in_array($requisicion->estatus, [RequisicionEstatus::Convertida, RequisicionEstatus::Cancelada], true)) {
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
     * y exige que cada partida tenga al menos una seleccion cuya cantidad
     * total no exceda la cantidad solicitada.
     */
    public function enviarAprobacion(Request $request, Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        if ($requisicion->estatus !== RequisicionEstatus::Cotizada) {
            return back()->withErrors(['estatus' => 'La requisición debe estar cotizada para enviarse a aprobación.']);
        }

        $requisicion->load('detalles.selecciones');

        foreach ($requisicion->detalles as $detalle) {
            $sumaSelecciones = (float) $detalle->selecciones->sum('cantidad');
            $cantidadPartida = (float) $detalle->cantidad;

            if ($sumaSelecciones <= 0.0) {
                return back()->withErrors([
                    'selecciones' => "La partida \"{$detalle->descripcion}\" no tiene proveedor asignado.",
                ]);
            }

            if ($sumaSelecciones > $cantidadPartida + 0.001) {
                return back()->withErrors([
                    'selecciones' => "La partida \"{$detalle->descripcion}\" tiene selecciones por encima de la cantidad solicitada.",
                ]);
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
     * Genera N ordenes de compra (1 por proveedor) desde una requisicion
     * aprobada. Cada seleccion lleva su `obra_rubro_id` capturado en este
     * paso. Registra trazabilidad via `requisicion_id` y `requisicion_detalle_id`.
     * Aplica impacto presupuestal por OC. Idempotente: si ya hay OCs
     * generadas, bloquea.
     */
    public function generarOrdenes(GenerarOrdenesRequest $request, Requisicion $requisicion): RedirectResponse
    {
        if ($requisicion->estatus !== RequisicionEstatus::Aprobada) {
            return back()->withErrors(['estatus' => 'Solo requisiciones aprobadas pueden generar OCs.']);
        }

        if ($requisicion->ordenesGeneradas()->exists()) {
            return back()->withErrors(['estatus' => 'Esta requisición ya tiene OCs generadas.']);
        }

        $rubroPorSeleccion = collect($request->input('rubros', []))
            ->keyBy('seleccion_id')
            ->map(fn ($r) => (int) $r['obra_rubro_id']);

        $requisicion->load('detalles.selecciones.cotizacionPrecio', 'detalles.selecciones.proveedor');

        // Verificar que todas las selecciones traen rubro
        $todasSelecciones = $requisicion->detalles->flatMap->selecciones;
        foreach ($todasSelecciones as $sel) {
            if (! $rubroPorSeleccion->has($sel->id)) {
                return back()->withErrors([
                    'rubros' => "Falta asignar rubro a una selección (#{$sel->id}).",
                ]);
            }
        }

        $userId = $request->user()->id;

        DB::transaction(function () use ($requisicion, $todasSelecciones, $rubroPorSeleccion, $request, $userId) {
            // Agrupar selecciones por proveedor -> 1 OC por grupo
            $porProveedor = $todasSelecciones->groupBy('proveedor_id');

            foreach ($porProveedor as $proveedorId => $selecciones) {
                $total = $selecciones->reduce(function ($acc, RequisicionSeleccion $s) {
                    $precio = (float) ($s->cotizacionPrecio?->precio_unitario ?? 0);

                    return $acc + $precio * (float) $s->cantidad;
                }, 0.0);

                $oc = OrdenCompra::create([
                    'requisicion_id' => $requisicion->id,
                    'proveedor_id' => $proveedorId,
                    'departamento_id' => $requisicion->departamento_id,
                    'creado_por' => $userId,
                    'moneda' => $request->string('moneda'),
                    'total' => round($total, 2),
                    'fecha_entrega_esperada' => $request->input('fecha_entrega_esperada'),
                    'notas' => $request->input('notas'),
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
                        'obra_rubro_id' => $rubroPorSeleccion[$sel->id],
                        'descripcion' => $detalle->descripcion,
                        'unidad' => $detalle->unidad,
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precioUnit,
                        'subtotal' => $subtotal,
                    ]);

                    // Marca la seleccion con su rubro y la OC partida creada
                    $sel->update([
                        'obra_rubro_id' => $rubroPorSeleccion[$sel->id],
                        'orden_compra_detalle_id' => $ocDetalle->id,
                    ]);
                }

                $oc->load('detalles');
                $oc->aplicarImpactoPresupuestal($userId);
            }

            $requisicion->transitionTo(RequisicionEstatus::Convertida);
        });

        return to_route('admin.costos.requisiciones.show', $requisicion)
            ->with('success', 'Órdenes de compra generadas correctamente.');
    }
}
