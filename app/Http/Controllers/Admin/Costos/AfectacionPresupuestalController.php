<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\AfectacionEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\AfectacionPresupuestalStoreRequest;
use App\Http\Requests\Admin\Costos\AfectacionPresupuestalUpdateRequest;
use App\Http\Requests\Admin\Costos\CancelarRequest;
use App\Models\Costos\AfectacionDetalle;
use App\Models\Costos\AfectacionPresupuestal;
use App\Models\Costos\ObraRubro;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Services\Costos\AcumuladoLedger;
use App\Support\OrdenaColumnas;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AfectacionPresupuestalController extends Controller
{
    use OrdenaColumnas;

    public function index(Request $request): Response
    {
        $query = AfectacionPresupuestal::query()
            ->with(['departamento', 'proveedor', 'creadoPor'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('folio', 'like', "%{$search}%")
                        ->orWhere('descripcion', 'like', "%{$search}%");
                });
            })
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e));

        $orden = $this->aplicarOrden($query, $request, [
            'folio' => 'folio',
            'fecha' => 'fecha',
            'tipo_origen' => 'tipo_origen',
            'monto_total' => 'monto_total',
            'estatus' => 'estatus',
            'departamento' => fn (Builder $q, string $dir) => $q->orderBy(
                Departamento::select('descripcion')->whereColumn('departamentos.id', 'costos_afectaciones_presupuestales.departamento_id'), $dir),
        ], 'created_at', 'desc');

        $afectaciones = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/costos/afectaciones/index', [
            'afectaciones' => $afectaciones,
            'filters' => $request->only('search', 'estatus'),
            'sortBy' => $orden['by'],
            'sortDir' => $orden['dir'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/costos/afectaciones/create', [
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'proveedores' => Proveedor::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
            'obras' => Obra::orderBy('no')->get(['id', 'no', 'descripcion']),
            'obraRubros' => ObraRubro::with('rubro')->get(),
        ]);
    }

    public function store(AfectacionPresupuestalStoreRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $afectacion = AfectacionPresupuestal::create([
                ...$request->safe()->except('detalles'),
                'creado_por' => $request->user()->id,
                'estatus' => 'borrador',
            ]);

            $montoTotal = 0;

            foreach ($request->input('detalles', []) as $detalle) {
                $monto = round((float) $detalle['cantidad'] * (float) $detalle['precio_unitario'], 2);
                $afectacion->detalles()->create([
                    'obra_rubro_id' => $detalle['obra_rubro_id'],
                    'concepto' => $detalle['concepto'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'monto' => $monto,
                ]);
                $montoTotal += $monto;
            }

            $afectacion->update(['monto_total' => $montoTotal]);

            $afectacion->historial()->create([
                'estatus_anterior' => '',
                'estatus_nuevo' => 'borrador',
                'fecha' => now(),
                'usuario_id' => $request->user()->id,
            ]);
        });

        return to_route('admin.costos.afectaciones.index');
    }

    public function show(AfectacionPresupuestal $afectacion): Response
    {
        $afectacion->load([
            'departamento',
            'proveedor',
            'creadoPor',
            'aprobadoPor',
            'detalles.obraRubro.rubro',
            'historial.usuario',
            'rubrosAfectados.obraRubro.rubro',
        ]);

        return Inertia::render('admin/costos/afectaciones/show', [
            'afectacion' => $afectacion,
        ]);
    }

    public function edit(AfectacionPresupuestal $afectacion): Response|RedirectResponse
    {
        if ($afectacion->estatus !== AfectacionEstatus::Borrador) {
            return to_route('admin.costos.afectaciones.show', $afectacion);
        }

        $afectacion->load(['detalles.obraRubro.rubro', 'lockedBy:id,name']);

        return Inertia::render('admin/costos/afectaciones/edit', [
            'afectacion' => $afectacion,
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'proveedores' => Proveedor::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
            'obras' => Obra::orderBy('no')->get(['id', 'no', 'descripcion']),
            'obraRubros' => ObraRubro::with('rubro')->get(),
        ]);
    }

    public function update(AfectacionPresupuestalUpdateRequest $request, AfectacionPresupuestal $afectacion): RedirectResponse
    {
        if ($afectacion->estatus !== AfectacionEstatus::Borrador) {
            return back()->withErrors(['estatus' => 'Solo se pueden editar afectaciones en borrador.']);
        }

        $afectacion->assertVersion($request->input('_version'));

        DB::transaction(function () use ($request, $afectacion) {
            $afectacion->update($request->safe()->except('detalles'));

            $incomingIds = collect($request->input('detalles', []))
                ->pluck('id')
                ->filter()
                ->all();

            $afectacion->detalles()
                ->whereNotIn('id', $incomingIds)
                ->delete();

            $montoTotal = 0;

            foreach ($request->input('detalles', []) as $detalle) {
                $monto = round((float) $detalle['cantidad'] * (float) $detalle['precio_unitario'], 2);

                if (! empty($detalle['id'])) {
                    AfectacionDetalle::where('id', $detalle['id'])->update([
                        'obra_rubro_id' => $detalle['obra_rubro_id'],
                        'concepto' => $detalle['concepto'],
                        'cantidad' => $detalle['cantidad'],
                        'precio_unitario' => $detalle['precio_unitario'],
                        'monto' => $monto,
                    ]);
                } else {
                    $afectacion->detalles()->create([
                        'obra_rubro_id' => $detalle['obra_rubro_id'],
                        'concepto' => $detalle['concepto'],
                        'cantidad' => $detalle['cantidad'],
                        'precio_unitario' => $detalle['precio_unitario'],
                        'monto' => $monto,
                    ]);
                }

                $montoTotal += $monto;
            }

            $afectacion->update(['monto_total' => $montoTotal]);
            $afectacion->unlock();
        });

        return to_route('admin.costos.afectaciones.index');
    }

    public function destroy(AfectacionPresupuestal $afectacion): RedirectResponse
    {
        if ($afectacion->estatus !== AfectacionEstatus::Borrador) {
            return back()->withErrors(['estatus' => 'Solo se pueden eliminar afectaciones en borrador.']);
        }

        $afectacion->delete();

        return to_route('admin.costos.afectaciones.index');
    }

    public function generarPdf(AfectacionPresupuestal $afectacion): HttpResponse
    {
        $afectacion->load([
            'departamento',
            'proveedor',
            'creadoPor',
            'detalles.obraRubro.rubro',
        ]);

        $estatusAnterior = $afectacion->estatus->value;

        if ($afectacion->estatus === AfectacionEstatus::Borrador) {
            $afectacion->transitionTo(AfectacionEstatus::PendienteFirma);

            $afectacion->historial()->create([
                'estatus_anterior' => $estatusAnterior,
                'estatus_nuevo' => 'pendiente_firma',
                'fecha' => now(),
                'usuario_id' => auth()->id(),
            ]);
        }

        $pdf = Pdf::loadView('pdf.costos.formato-afectacion', [
            'afectacion' => $afectacion,
        ])->setPaper('letter', 'portrait')
            ->setOption('margin-top', 30)
            ->setOption('margin-bottom', 60)
            ->setOption('margin-left', 60)
            ->setOption('margin-right', 60);

        $filename = "afectacion-{$afectacion->folio}.pdf";

        return $pdf->download($filename);
    }

    public function uploadFirmado(Request $request, AfectacionPresupuestal $afectacion): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        if ($afectacion->estatus !== AfectacionEstatus::PendienteFirma) {
            return back()->withErrors(['estatus' => 'La afectación debe estar en pendiente de firma.']);
        }

        $path = $request->file('archivo')->store('costos/afectaciones-firmados', 'public');

        $estatusAnterior = $afectacion->estatus->value;

        $afectacion->update([
            'pdf_firmado_path' => $path,
            'estatus' => 'aprobada',
            'aprobado_por' => $request->user()->id,
            'fecha_aprobacion' => now(),
        ]);

        $afectacion->historial()->create([
            'estatus_anterior' => $estatusAnterior,
            'estatus_nuevo' => 'aprobada',
            'fecha' => now(),
            'usuario_id' => $request->user()->id,
        ]);

        // Apply budget impact and create rubros afectados
        foreach ($afectacion->detalles as $detalle) {
            $obraRubro = app(AcumuladoLedger::class)->registrarPorId(
                $detalle->obra_rubro_id,
                (float) $detalle->monto,
                motivo: $detalle->concepto,
                userId: $request->user()->id,
            );

            $afectacion->rubrosAfectados()->create([
                'obra_rubro_id' => $detalle->obra_rubro_id,
                'monto' => $detalle->monto,
                'sobre_giro' => $obraRubro->disponible < 0,
                'descripcion' => $detalle->concepto,
                'tipo_movimiento' => 'cargo',
                'estatus' => 'aplicado',
                'usuario_aplica_id' => $request->user()->id,
                'fecha_aplicacion' => now(),
            ]);
        }

        return back()->with('success', 'Afectación aprobada correctamente.');
    }

    public function cancelar(CancelarRequest $request, AfectacionPresupuestal $afectacion): RedirectResponse
    {
        if (! in_array($afectacion->estatus, [AfectacionEstatus::PendienteFirma, AfectacionEstatus::Aprobada], true)) {
            return back()->withErrors(['estatus' => 'Solo se pueden cancelar afectaciones pendientes o aprobadas.']);
        }

        $estatusAnterior = $afectacion->estatus->value;

        // Revert budget impact if was approved
        if ($afectacion->estatus === AfectacionEstatus::Aprobada) {
            foreach ($afectacion->detalles as $detalle) {
                app(AcumuladoLedger::class)->registrarPorId(
                    $detalle->obra_rubro_id,
                    -(float) $detalle->monto,
                    motivo: 'Cancelación de afectación',
                    userId: auth()->id(),
                );
            }

            $afectacion->rubrosAfectados()->create([
                'obra_rubro_id' => $afectacion->detalles->first()?->obra_rubro_id ?? 0,
                'monto' => $afectacion->monto_total,
                'descripcion' => 'Cancelación de afectación',
                'tipo_movimiento' => 'abono',
                'estatus' => 'cancelado',
                'usuario_aplica_id' => auth()->id(),
                'fecha_aplicacion' => now(),
            ]);
        }

        $afectacion->transitionTo(AfectacionEstatus::Cancelada);
        $afectacion->registrarCancelacion($request->validated('motivo'), $request->user()->id);

        $afectacion->historial()->create([
            'estatus_anterior' => $estatusAnterior,
            'estatus_nuevo' => 'cancelada',
            'fecha' => now(),
            'usuario_id' => auth()->id(),
        ]);

        return back()->with('success', 'Afectación cancelada.');
    }
}
