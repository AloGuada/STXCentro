<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\OrdenCompraStoreRequest;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrdenCompraController extends Controller
{
    public function index(Request $request): Response
    {
        $ordenes = OrdenCompra::query()
            ->with(['proveedor:id,razon_social,nombre_comercial', 'departamento:id,descripcion'])
            ->withCount('facturas')
            ->addSelect([
                'entregas_count' => DB::table('costos_entregas')
                    ->join('costos_facturas', 'costos_facturas.id', '=', 'costos_entregas.factura_id')
                    ->whereColumn('costos_facturas.orden_compra_id', 'costos_ordenes_compra.id')
                    ->selectRaw('count(*)'),
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
                        ->orWhereHas('proveedor', fn ($p) => $p->where('razon_social', 'like', "%{$search}%"));
                });
            })
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/costos/ordenes-compra/index', [
            'ordenes' => $ordenes,
            'filters' => $request->only('search', 'estatus'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/costos/ordenes-compra/create', [
            'proveedores' => Proveedor::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
            'obras' => Obra::orderBy('no')->get(['id', 'no', 'descripcion']),
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'obraRubros' => ObraRubro::with('rubro')->get(),
        ]);
    }

    public function store(OrdenCompraStoreRequest $request): RedirectResponse
    {
        $warnings = [];
        $oc = null;

        DB::transaction(function () use ($request, &$warnings, &$oc) {
            $oc = OrdenCompra::create([
                ...$request->safe()->except(['detalles', 'archivo']),
                'creado_por' => $request->user()->id,
                'estatus' => 'pendiente_factura',
            ]);

            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $oc->media()->create([
                    'descripcion' => 'archivo',
                    'nombre_original' => $file->getClientOriginalName(),
                    'path' => $file->store('costos/ordenes-compra', 'public'),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            foreach ($request->input('detalles', []) as $detalle) {
                $monto = (float) $detalle['monto'];
                $oc->detalles()->create([
                    'obra_rubro_id' => $detalle['obra_rubro_id'],
                    'monto' => $monto,
                ]);

                $obraRubro = ObraRubro::find($detalle['obra_rubro_id']);
                if ($obraRubro) {
                    $disponible = (float) $obraRubro->presupuestado - (float) $obraRubro->acumulado;
                    if ($monto > $disponible) {
                        $warnings[] = "El rubro {$obraRubro->rubro?->codigo} excede el presupuesto disponible.";
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

    public function show(OrdenCompra $ordenCompra): Response
    {
        $ordenCompra->load([
            'proveedor',
            'obra',
            'departamento',
            'creador',
            'detalles.obraRubro.rubro',
            'detalles.obraRubro.obra',
            'facturas.entregas.media',
            'media',
            'rubrosAfectados.obraRubro.rubro',
        ]);

        return Inertia::render('admin/costos/ordenes-compra/show', [
            'ordenCompra' => $ordenCompra,
        ]);
    }

    public function destroy(OrdenCompra $ordenCompra): RedirectResponse
    {
        if ($ordenCompra->facturas()->exists()) {
            return back()->withErrors(['estatus' => 'No se puede eliminar una orden con facturas asociadas.']);
        }

        DB::transaction(function () use ($ordenCompra) {
            if (in_array($ordenCompra->estatus, ['pendiente_factura', 'pendiente_entrega', 'pendiente_aprobacion'])) {
                $ordenCompra->load('detalles');
                $ordenCompra->revertirImpactoPresupuestal();
            }

            $ordenCompra->detalles()->delete();
            $ordenCompra->delete();
        });

        return to_route('admin.costos.ordenes-compra.index');
    }

    public function cancelar(OrdenCompra $ordenCompra): RedirectResponse
    {
        Gate::authorize('costos.ordenes-compra.cancelar');

        if (! in_array($ordenCompra->estatus, ['pendiente_factura', 'pendiente_entrega', 'pendiente_aprobacion'])) {
            return back()->withErrors(['estatus' => 'Solo se pueden cancelar órdenes pendientes.']);
        }

        DB::transaction(function () use ($ordenCompra) {
            $ordenCompra->load('detalles');
            $ordenCompra->revertirImpactoPresupuestal();
            $ordenCompra->update(['estatus' => 'cancelada']);
        });

        return back()->with('success', 'Orden de compra cancelada.');
    }
}
