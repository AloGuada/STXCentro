<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\DestajoStoreRequest;
use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Proceso;
use App\Models\Prod\Registro;
use App\Models\Prod\TipoPagoExtra;
use App\Services\Prod\AsistenciaDelDestajo;
use App\Services\Prod\AvanceDePiezas;
use App\Services\Prod\GeneradorLiquidaciones;
use App\Services\Prod\PendientesDeLiquidar;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DestajoController extends Controller
{
    public function index(Request $request): Response
    {
        $destajos = Destajo::query()
            ->withCount('liquidaciones')
            ->when($request->search, fn ($q, $s) => $q->where('semana', 'like', "%{$s}%")->orWhere('anio', 'like', "%{$s}%"))
            ->orderByDesc('anio')
            ->orderByDesc('semana')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/destajos/index', [
            'destajos' => $destajos,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/destajos/create', [
            'anioSugerido' => now()->year,
            'semanaSugerida' => min(now()->weekOfYear, 52),
        ]);
    }

    public function store(DestajoStoreRequest $request): RedirectResponse
    {
        $destajo = Destajo::create($request->validated());

        return to_route('admin.prod.destajos.show', $destajo);
    }

    public function show(
        Destajo $destajo,
        GeneradorLiquidaciones $generador,
        AvanceDePiezas $avance,
        PendientesDeLiquidar $pendientes,
        AsistenciaDelDestajo $asistencia,
    ): Response {
        $destajo->load([
            'liquidaciones.grupoTrabajo',
            'liquidaciones.detalles',
            'liquidaciones.empleados',
            'liquidaciones.generador',
        ]);

        $data = ['destajo' => $destajo];

        if ($destajo->cerrado) {
            return Inertia::render('admin/prod/destajos/show', $data);
        }

        $data['registrosPreview'] = Registro::query()
            ->with(['pieza.marca.obra', 'proceso', 'grupoTrabajo'])
            ->whereBetween('fecha', [$destajo->fecha_inicio, $destajo->fecha_fin])
            ->orderByDesc('fecha')
            ->get()
            ->groupBy('grupo_trabajo_id');

        $data['pagosExtraPreview'] = PagoExtra::query()
            ->with(['tipo', 'grupoTrabajo'])
            ->where('destajo_id', $destajo->id)
            ->get()
            ->groupBy('grupo_trabajo_id');

        $data['piezasSinPrecio'] = $generador->piezasSinPrecio($destajo);
        $data['gruposTrabajo'] = GrupoTrabajo::where('activo', true)->orderBy('descripcion')->get();
        // El catalogo de captura: las marcas del catalogo vigente con sus piezas,
        // para que el formulario ofrezca marca -> etapa -> QS.
        $data['marcas'] = Concepto::query()
            ->with(['obra:id,no,descripcion', 'piezas' => fn ($q) => $q->where('activo', true)->orderBy('qs')])
            ->deCatalogoVigente()
            ->where('activo', true)
            ->orderBy('marca')
            ->orderBy('etapa')
            ->get();

        $procesos = Proceso::activos()->orderBy('orden')->get();
        $data['procesos'] = $procesos;
        $data['procesosPorObra'] = Obra::query()
            ->whereIn('id', $data['marcas']->pluck('obra_id')->unique())
            ->with('procesos:id')
            ->get()
            ->mapWithKeys(fn (Obra $obra) => [$obra->id => $obra->procesos->pluck('id')]);

        $data['avance'] = $avance->decorar(
            $data['marcas']->flatMap->piezas,
            $procesos->pluck('id')->all(),
        )->mapWithKeys(fn ($pieza) => [$pieza->id => $pieza->avance]);
        $data['tipos'] = TipoPagoExtra::orderBy('orden')->get();
        $data['pendientes'] = $pendientes->paraDestajo($destajo);
        $data['asistenciaFaltante'] = $asistencia->faltantes($destajo);

        return Inertia::render('admin/prod/destajos/show', $data);
    }

    public function destroy(Destajo $destajo): RedirectResponse
    {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'No se puede eliminar un destajo cerrado.']);
        }

        $destajo->liquidaciones()->delete();
        $destajo->pagosExtra()->delete();
        $destajo->delete();

        return to_route('admin.prod.destajos.index');
    }

    public function ordenPagoPdf(Destajo $destajo, GeneradorLiquidaciones $generador): HttpResponse
    {
        $grupos = $generador->ordenDePago($destajo);

        $pdf = Pdf::loadView('pdf.prod.orden-pago', [
            'destajo' => $destajo,
            'grupos' => $grupos,
        ])->setPaper('letter', 'landscape');

        return $pdf->stream("orden-pago-{$destajo->anio}-S{$destajo->semana}.pdf");
    }

    public function cerrar(
        Destajo $destajo,
        GeneradorLiquidaciones $generador,
        AsistenciaDelDestajo $asistencia,
    ): RedirectResponse {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'Este destajo ya esta cerrado.']);
        }

        // Parte del pago va a salario base y depende de los dias trabajados:
        // sin asistencia completa no se puede liquidar la semana.
        $faltantes = $asistencia->faltantes($destajo);

        if ($faltantes->isNotEmpty()) {
            $detalle = $faltantes
                ->map(fn (array $f) => $f['grupo'].' ('.implode(', ', $f['empleados']).')')
                ->implode('; ');

            return back()->withErrors([
                'error' => "Falta capturar la asistencia antes de cerrar: {$detalle}.",
            ]);
        }

        $generador->generar($destajo);

        return back();
    }
}
