<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\PagoEstatus;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\AbonoComprobanteRequest;
use App\Http\Requests\Admin\Costos\CancelarRequest;
use App\Http\Requests\Admin\Costos\ParcializarRequest;
use App\Mail\PagoProgramadoMail;
use App\Models\Costos\Factura;
use App\Models\Costos\Pago;
use App\Models\Costos\SolicitudPago;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

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
            ->when($request->orden_compra_id, function ($q, $ocId) {
                $q->where('pagable_type', Factura::class)
                    ->whereIn('pagable_id', Factura::where('orden_compra_id', $ocId)->select('id'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/costos/pagos/index', [
            'pagos' => $pagos,
            'filters' => $request->only('search', 'estatus', 'tipo_pago', 'orden_compra_id'),
        ]);
    }

    public function show(Pago $pago): Response
    {
        $pago->load(['pagable.proveedor', 'pagosParciales.media', 'pagoPadre', 'media']);

        return Inertia::render('admin/costos/pagos/show', [
            'pago' => $pago,
        ]);
    }

    public function programar(Request $request, Pago $pago): RedirectResponse
    {
        Gate::authorize('costos.pagos.programar');

        if ($pago->estatus !== PagoEstatus::Pendiente) {
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
        if ($pago->estatus !== PagoEstatus::Programado) {
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
        if ($pago->estatus !== PagoEstatus::Programado) {
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

            $pago->transitionTo(PagoEstatus::Parcial);
        });

        return back()->with('success', 'Pago parcializado correctamente.');
    }

    public function uploadComprobante(AbonoComprobanteRequest $request, Pago $pago): RedirectResponse
    {
        if ($pago->estatus !== PagoEstatus::Programado) {
            return back()->withErrors(['estatus' => 'Solo se puede subir comprobante a un pago programado.']);
        }

        if ($pago->tieneParcialidades()) {
            return back()->withErrors(['tipo_pago' => 'Este pago ya fue parcializado. Suba comprobantes por parcialidad.']);
        }

        $file = $request->file('comprobante');
        $path = $file->store('costos/pagos/comprobantes', 'public');

        DB::transaction(function () use ($pago, $file, $path, $request) {
            $pago->media()->create([
                'descripcion' => DocumentoTipo::ComprobantePago->value,
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            $pago->update([
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

    public function cancelar(CancelarRequest $request, Pago $pago): RedirectResponse
    {
        Gate::authorize('costos.pagos.cancelar');

        if (in_array($pago->estatus, [PagoEstatus::Pagado, PagoEstatus::Cancelado], true)) {
            return back()->withErrors(['estatus' => 'Este pago ya está '.$pago->estatus->value.'.']);
        }

        if ($pago->media()->exists()) {
            return back()->withErrors(['estatus' => 'No se puede cancelar un pago con comprobante. Elimine el comprobante primero.']);
        }

        DB::transaction(function () use ($pago, $request) {
            $pago->transitionTo(PagoEstatus::Cancelado);
            $pago->registrarCancelacion($request->validated('motivo'), $request->user()->id);
        });

        return back()->with('success', 'Pago cancelado.');
    }

    private function checkAndMarkParentAsPaid(Pago $parent): void
    {
        $pendientes = $parent->pagosParciales()->where('estatus', '!=', 'pagado')->count();

        if ($pendientes > 0) {
            return;
        }

        $parent->update(['fecha_pago_realizada' => now()]);
        $parent->transitionTo(PagoEstatus::Pagado);

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
            $pagable->transitionTo(FacturaEstatus::Pagada);
            $pagable->ordenCompra->recalcularEstatus();
        } elseif ($pagable instanceof SolicitudPago) {
            $pagable->update(['fecha_pago_realizada' => now()]);
            $pagable->transitionTo(SolicitudPagoEstatus::Pagada);
        }
    }

    public function reporte(Request $request): HttpResponse
    {
        $validated = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        $fechaInicio = Carbon::parse($validated['fecha_inicio'])->startOfDay();
        $fechaFin = Carbon::parse($validated['fecha_fin'])->endOfDay();

        $pagos = Pago::query()
            ->whereNull('pago_padre_id')
            ->whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->with(['pagable.proveedor'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pdf = Pdf::loadView('pdf.costos.reporte-pagos', [
            'pagos' => $pagos,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
        ])->setPaper('letter', 'landscape');

        $filename = 'reporte-pagos-'.$fechaInicio->format('Ymd').'-'.$fechaFin->format('Ymd').'.pdf';

        return $pdf->stream($filename);
    }
}
