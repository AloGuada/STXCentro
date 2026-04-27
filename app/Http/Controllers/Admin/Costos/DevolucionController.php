<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\DevolucionEstatus;
use App\Enums\Costos\DocumentoTipo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\CancelarRequest;
use App\Http\Requests\Admin\Costos\DevolucionStoreRequest;
use App\Models\Costos\Devolucion;
use App\Models\Costos\EntregaDetalle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DevolucionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('costos.devoluciones.ver');

        $devoluciones = Devolucion::query()
            ->with([
                'entregaDetalle.entrega:id,orden_compra_id,fecha_entrega',
                'entregaDetalle.ordenCompraDetalle:id,orden_compra_id,descripcion,unidad',
                'entregaDetalle.entrega.ordenCompra:id,folio,proveedor_id',
                'entregaDetalle.entrega.ordenCompra.proveedor:id,razon_social',
                'creador:id,name',
            ])
            ->when($request->search, fn ($q, $s) => $q->where(function ($qq) use ($s) {
                $qq->where('folio', 'like', "%{$s}%")
                    ->orWhere('motivo', 'like', "%{$s}%");
            }))
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/costos/devoluciones/index', [
            'devoluciones' => $devoluciones,
            'filters' => $request->only('search', 'estatus'),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('costos.devoluciones.crear');

        // Lista de partidas recibidas con saldo no devuelto > 0, agrupadas por OC.
        $entregaDetalles = EntregaDetalle::query()
            ->with([
                'entrega:id,orden_compra_id,fecha_entrega',
                'entrega.ordenCompra:id,folio,proveedor_id',
                'entrega.ordenCompra.proveedor:id,razon_social',
                'ordenCompraDetalle:id,descripcion,unidad',
                'devoluciones',
            ])
            ->get()
            ->map(function (EntregaDetalle $ed) {
                $devueltaVigente = (float) $ed->devoluciones
                    ->where('estatus', DevolucionEstatus::Vigente->value)
                    ->sum('cantidad');
                $disponible = (float) $ed->cantidad_recibida - $devueltaVigente;

                return [
                    'id' => $ed->id,
                    'oc_folio' => $ed->entrega?->ordenCompra?->folio,
                    'oc_id' => $ed->entrega?->ordenCompra?->id,
                    'proveedor' => $ed->entrega?->ordenCompra?->proveedor?->razon_social,
                    'fecha_entrega' => $ed->entrega?->fecha_entrega?->format('Y-m-d'),
                    'partida_descripcion' => $ed->ordenCompraDetalle?->descripcion,
                    'unidad' => $ed->ordenCompraDetalle?->unidad,
                    'cantidad_recibida' => (float) $ed->cantidad_recibida,
                    'cantidad_disponible' => $disponible,
                ];
            })
            ->filter(fn ($row) => $row['cantidad_disponible'] > 0.001)
            ->values();

        return Inertia::render('admin/costos/devoluciones/create', [
            'entregaDetalles' => $entregaDetalles,
            'preselectId' => $request->integer('entrega_detalle_id') ?: null,
        ]);
    }

    public function store(DevolucionStoreRequest $request): RedirectResponse
    {
        $entregaDetalle = EntregaDetalle::findOrFail($request->integer('entrega_detalle_id'));
        $cantidad = (float) $request->input('cantidad');

        $devueltaPrevia = (float) $entregaDetalle->devoluciones()
            ->where('estatus', DevolucionEstatus::Vigente->value)
            ->sum('cantidad');
        $disponible = (float) $entregaDetalle->cantidad_recibida - $devueltaPrevia;

        if ($cantidad > $disponible + 0.001) {
            return back()
                ->withInput()
                ->withErrors([
                    'cantidad' => sprintf(
                        'La cantidad excede lo recibido pendiente de devolver (%.2f).',
                        $disponible,
                    ),
                ]);
        }

        $devolucion = DB::transaction(function () use ($request, $entregaDetalle, $cantidad) {
            $devolucion = Devolucion::create([
                'entrega_detalle_id' => $entregaDetalle->id,
                'cantidad' => $cantidad,
                'motivo' => $request->string('motivo'),
                'fecha' => $request->input('fecha'),
                'estatus' => DevolucionEstatus::Vigente->value,
                'creado_por' => $request->user()->id,
            ]);

            if ($request->hasFile('evidencia')) {
                $file = $request->file('evidencia');
                $devolucion->media()->create([
                    'descripcion' => DocumentoTipo::EvidenciaDevolucion->value,
                    'nombre_original' => $file->getClientOriginalName(),
                    'path' => $file->store('devoluciones', 'public'),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            return $devolucion;
        });

        return to_route('admin.costos.devoluciones.show', $devolucion)
            ->with('success', 'Devolución registrada correctamente.');
    }

    public function show(Devolucion $devolucion): Response
    {
        Gate::authorize('costos.devoluciones.ver');

        $devolucion->load([
            'entregaDetalle.entrega.ordenCompra:id,folio,proveedor_id',
            'entregaDetalle.entrega.ordenCompra.proveedor:id,razon_social',
            'entregaDetalle.ordenCompraDetalle:id,descripcion,unidad,cantidad',
            'creador:id,name',
            'media',
            'evidencia',
            'activities.causer',
        ]);

        return Inertia::render('admin/costos/devoluciones/show', [
            'devolucion' => $devolucion,
        ]);
    }

    public function cancelar(CancelarRequest $request, Devolucion $devolucion): RedirectResponse
    {
        Gate::authorize('costos.devoluciones.cancelar');

        if ($devolucion->estatus !== DevolucionEstatus::Vigente) {
            return back()->withErrors(['estatus' => 'Solo se pueden cancelar devoluciones vigentes.']);
        }

        DB::transaction(function () use ($devolucion, $request) {
            $devolucion->update(['motivo_cancelacion' => $request->validated('motivo')]);
            $devolucion->transitionTo(DevolucionEstatus::Cancelada);
        });

        return back()->with('success', 'Devolución cancelada.');
    }
}
