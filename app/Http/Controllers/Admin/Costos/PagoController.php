<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\AbonoComprobanteRequest;
use App\Http\Requests\Admin\Costos\ParcializarRequest;
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

class PagoController extends Controller
{
    public function index(Request $request): Response
    {
        $pagos = Pago::query()
            ->whereNull('pago_padre_id')
            ->with('pagable')
            ->when($request->search, function ($query, $search) {
                $query->where('folio', 'like', "%{$search}%");
            })
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->when($request->tipo_pago, fn ($q, $t) => $q->where('tipo_pago', $t))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/costos/pagos/index', [
            'pagos' => $pagos,
            'filters' => $request->only('search', 'estatus', 'tipo_pago'),
        ]);
    }

    public function show(Pago $pago): Response
    {
        $pago->load(['pagable.proveedor', 'pagosParciales', 'pagoPadre']);

        return Inertia::render('admin/costos/pagos/show', [
            'pago' => $pago,
        ]);
    }

    public function programar(Request $request, Pago $pago): RedirectResponse
    {
        Gate::authorize('costos.pagos.programar');

        if ($pago->estatus !== 'pendiente') {
            return back()->withErrors(['estatus' => 'Solo se puede programar un pago pendiente.']);
        }

        $pagable = $pago->pagable;
        $proveedor = method_exists($pagable, 'proveedor') ? $pagable->proveedor : null;
        $diasCredito = $proveedor?->dias_credito_default ?? 0;

        $fechaBase = Carbon::today()->addDays($diasCredito);

        // Ajustar al viernes >= más cercano
        if ($fechaBase->dayOfWeek === Carbon::FRIDAY) {
            $fechaPago = $fechaBase;
        } else {
            $fechaPago = $fechaBase->next(Carbon::FRIDAY);
        }

        $pago->update([
            'fecha_pago_programada' => $fechaPago,
            'estatus' => 'programado',
        ]);

        if ($proveedor && $proveedor->email) {
            Mail::to($proveedor->email)->send(new PagoProgramadoMail($pago, $proveedor));
        }

        return back()->with('success', 'Pago programado para '.$fechaPago->format('d/m/Y').'.');
    }

    public function showParcializar(Pago $pago): Response|RedirectResponse
    {
        if ($pago->tipo_pago !== 'credito') {
            return back()->withErrors(['tipo_pago' => 'Solo pagos a crédito pueden parcializarse.']);
        }

        if ($pago->estatus !== 'programado') {
            return back()->withErrors(['estatus' => 'Solo se puede parcializar un pago programado.']);
        }

        if ($pago->tieneParcialidades()) {
            return back()->withErrors(['parcialidades' => 'Este pago ya tiene parcialidades.']);
        }

        $pago->load('pagable.proveedor');

        return Inertia::render('admin/costos/pagos/parcializar', [
            'pago' => $pago,
        ]);
    }

    public function parcializar(ParcializarRequest $request, Pago $pago): RedirectResponse
    {
        if ($pago->estatus !== 'programado') {
            return back()->withErrors(['estatus' => 'Solo se puede parcializar un pago programado.']);
        }

        DB::transaction(function () use ($request, $pago) {
            foreach ($request->input('parcialidades') as $index => $parcialidad) {
                Pago::create([
                    'pagable_type' => $pago->pagable_type,
                    'pagable_id' => $pago->pagable_id,
                    'pago_padre_id' => $pago->id,
                    'numero_parcialidad' => $index + 1,
                    'monto_pago' => $parcialidad['monto'],
                    'moneda' => $pago->moneda,
                    'tipo_cambio' => $pago->tipo_cambio,
                    'tipo_pago' => $pago->tipo_pago,
                    'fecha_pago_programada' => $parcialidad['fecha_programada'],
                    'estatus' => 'programado',
                ]);
            }

            $pago->update(['estatus' => 'parcial']);
        });

        return back()->with('success', 'Pago parcializado correctamente.');
    }

    public function uploadComprobante(AbonoComprobanteRequest $request, Pago $pago): RedirectResponse
    {
        if ($pago->estatus !== 'programado') {
            return back()->withErrors(['estatus' => 'Solo se puede subir comprobante a un pago programado.']);
        }

        if ($pago->tieneParcialidades()) {
            return back()->withErrors(['tipo_pago' => 'Este pago ya fue parcializado. Suba comprobantes por parcialidad.']);
        }

        $path = $request->file('comprobante')->store('costos/pagos/comprobantes', 'public');

        DB::transaction(function () use ($pago, $path, $request) {
            $pago->update([
                'ruta_comprobante' => $path,
                'fecha_pago_realizada' => now(),
                'notas' => $request->input('notas'),
                'estatus' => 'pagado',
            ]);

            if ($pago->esHijo()) {
                $this->checkAndMarkParentAsPaid($pago->pagoPadre);
            } else {
                $this->markPagableAsPaid($pago);
            }
        });

        return back()->with('success', 'Comprobante subido y pago marcado como pagado.');
    }

    private function checkAndMarkParentAsPaid(Pago $parent): void
    {
        $pendientes = $parent->pagosParciales()->where('estatus', '!=', 'pagado')->count();

        if ($pendientes > 0) {
            return;
        }

        $parent->update([
            'fecha_pago_realizada' => now(),
            'estatus' => 'pagado',
        ]);

        if ($parent->esHijo()) {
            $this->checkAndMarkParentAsPaid($parent->pagoPadre);
        } else {
            $this->markPagableAsPaid($parent);
        }
    }

    private function markPagableAsPaid(Pago $pago): void
    {
        $pagable = $pago->pagable;

        if (! $pagable) {
            return;
        }

        if ($pagable instanceof Factura) {
            $pagable->update(['estatus' => 'pagada']);
            $pagable->ordenCompra->recalcularEstatus();
        } elseif (method_exists($pagable, 'update')) {
            $pagable->update([
                'estatus' => 'pagada',
                'fecha_pago_realizada' => now(),
            ]);
        }
    }
}
