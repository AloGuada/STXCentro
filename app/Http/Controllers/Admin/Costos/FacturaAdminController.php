<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Mail\FacturaAceptadaMail;
use App\Mail\PagoProgramadoMail;
use App\Models\Costos\Factura;
use App\Models\Costos\Pago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class FacturaAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $facturas = Factura::query()
            ->with(['proveedor:id,razon_social,nombre_comercial', 'ordenCompra:id,folio'])
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

        return Inertia::render('admin/costos/facturas/index', [
            'facturas' => $facturas,
            'filters' => $request->only('search', 'estatus'),
        ]);
    }

    public function show(Factura $factura): Response
    {
        $factura->load([
            'proveedor',
            'ordenCompra.detalles.obraRubro.rubro',
            'entregas.recibidoPor',
            'pago.pagosParciales',
            'aprobadaCostosPor',
            'aceptadaContabilidadPor',
        ]);

        return Inertia::render('admin/costos/facturas/show', [
            'factura' => $factura,
        ]);
    }

    public function aprobarCostos(Request $request, Factura $factura): RedirectResponse
    {
        if ($factura->estatus !== 'pendiente_entrega') {
            return back()->withErrors(['estatus' => 'La factura debe estar pendiente de entrega con entregas registradas.']);
        }

        if (! $factura->entregas()->exists()) {
            return back()->withErrors(['estatus' => 'La factura debe tener al menos una entrega registrada.']);
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

        if ($factura->estatus !== 'pendiente_pago') {
            return back()->withErrors(['estatus' => 'La factura debe estar pendiente de pago.']);
        }

        if (! $factura->aprobada_costos) {
            return back()->withErrors(['aprobada_costos' => 'La factura debe estar aprobada por costos.']);
        }

        if ($factura->aceptada_contabilidad) {
            return back()->withErrors(['aceptada_contabilidad' => 'La factura ya fue aceptada por contabilidad.']);
        }

        $pago = DB::transaction(function () use ($request, $factura) {
            $factura->update([
                'aceptada_contabilidad' => true,
                'aceptada_contabilidad_por' => $request->user()->id,
                'aceptada_contabilidad_at' => now(),
            ]);

            $proveedor = $factura->proveedor;
            $tipoPago = $proveedor && $proveedor->maneja_credito ? 'credito' : 'contado';
            $diasCredito = $proveedor?->dias_credito_default ?? 0;

            $fechaBase = Carbon::today()->addDays($diasCredito);
            $fechaPago = $fechaBase->dayOfWeek === Carbon::FRIDAY
                ? $fechaBase
                : $fechaBase->next(Carbon::FRIDAY);

            $pago = Pago::create([
                'pagable_type' => Factura::class,
                'pagable_id' => $factura->id,
                'monto_pago' => $factura->total,
                'moneda' => $factura->moneda,
                'tipo_pago' => $tipoPago,
                'fecha_pago_programada' => $fechaPago,
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
}
